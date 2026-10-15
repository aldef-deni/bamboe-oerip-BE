<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Villa;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Manajemen akun owner properti — HANYA superadmin (role admin).
 * Owner tidak bisa mendaftar sendiri; akun dibuat di sini lalu
 * owner login di owner.bamboeoerip.com.
 */
class OwnerController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('role', 'owner')
            ->withCount([
                'ownedVillas as villas_count',
                'ownedVillas as active_villas_count' => fn ($q) => $q->where('is_active', true),
                'ownedVillas as pending_villas_count' => fn ($q) => $q->where('review_status', 'pending'),
            ]);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest()->paginate((int) $request->query('per_page', 50)));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(), $this->messages());

        $owner = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'username' => $data['username'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => 'owner',
            'is_active' => $data['is_active'] ?? true,
        ]);
        $owner->forceFill(['email_verified_at' => now()])->save();

        NotificationService::admin('account', 'Owner baru didaftarkan', "Akun owner {$owner->name} ({$owner->username}) dibuat.", '/admin/owners');
        NotificationService::owner($owner->id, 'account', 'Selamat datang di Extranet Bamboe Oerip', 'Akun owner Anda sudah aktif. Mulai tambahkan properti akomodasi Anda dari menu Properti.', '/owner/properties/new');

        return response()->json($owner->loadCount('ownedVillas as villas_count'), 201);
    }

    public function update(Request $request, User $user)
    {
        $this->ensureOwner($user);

        $data = $request->validate($this->rules($user), $this->messages());

        $user->fill([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'username' => $data['username'],
            'phone' => $data['phone'] ?? null,
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $deactivated = false;
        if (array_key_exists('is_active', $data)) {
            $deactivated = $user->is_active && ! $data['is_active'];
            $user->is_active = (bool) $data['is_active'];
        }

        $user->save();

        // Nonaktif / ganti password -> keluarkan semua sesi owner
        if ($deactivated || ! empty($data['password'])) {
            $user->tokens()->delete();
        }

        if (! empty($data['password']) && ! $deactivated) {
            NotificationService::owner($user->id, 'account', 'Password diperbarui', 'Password akun owner Anda telah diubah oleh admin Bamboe Oerip.', '/owner/profile');
        }

        return response()->json($user->loadCount('ownedVillas as villas_count'));
    }

    /**
     * Hapus akun owner. Properti miliknya TIDAK ikut terhapus —
     * kepemilikannya kembali ke Bamboe Oerip (owner_id = null).
     */
    public function destroy(User $user)
    {
        $this->ensureOwner($user);

        $name = $user->name;
        $user->tokens()->delete();
        $user->ownedVillas()->update(['owner_id' => null]);
        $user->delete();

        NotificationService::admin('account', 'Owner dihapus', "Akun owner {$name} dihapus. Propertinya kini dikelola Bamboe Oerip.", '/admin/owners');

        return response()->json(['message' => 'Akun owner dihapus.']);
    }

    /**
     * Semua properti + pemiliknya saat ini, untuk dialog "Hubungkan Properti".
     */
    public function villas(User $user)
    {
        $this->ensureOwner($user);

        $villas = Villa::with(['images', 'owner:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'city', 'owner_id', 'is_active', 'review_status'])
            ->map(fn (Villa $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'type' => $v->type,
                'city' => $v->city,
                'is_active' => $v->is_active,
                'review_status' => $v->review_status,
                'owner_id' => $v->owner_id,
                'owner_name' => $v->owner?->name,
                'primary_image_url' => $v->primary_image_url,
            ]);

        return response()->json([
            'owner' => $user->only(['id', 'name', 'username']),
            'villas' => $villas,
        ]);
    }

    /**
     * Tautkan properti eksisting ke owner (sinkron): villa_ids = daftar akhir
     * properti milik owner. Properti yang tidak dicentang lagi dilepas
     * (kembali dikelola Bamboe Oerip). Properti milik owner lain dipindahkan.
     */
    public function syncVillas(Request $request, User $user)
    {
        $this->ensureOwner($user);

        $data = $request->validate([
            'villa_ids' => ['present', 'array'],
            'villa_ids.*' => ['integer', 'distinct', 'exists:villas,id'],
        ], [
            'villa_ids.*.exists' => 'Ada properti yang tidak ditemukan. Muat ulang halaman.',
        ]);

        $target = collect($data['villa_ids'])->map(fn ($id) => (int) $id)->unique();
        $current = $user->ownedVillas()->pluck('id');

        $addIds = $target->diff($current)->values();
        $removeIds = $current->diff($target)->values();

        // Catat pemilik lama properti yang dipindah, untuk notifikasi
        $moved = Villa::whereIn('id', $addIds)->whereNotNull('owner_id')->get(['id', 'name', 'owner_id']);
        $added = Villa::whereIn('id', $addIds)->get(['id', 'name']);
        $removed = Villa::whereIn('id', $removeIds)->get(['id', 'name']);

        DB::transaction(function () use ($user, $addIds, $removeIds) {
            if ($addIds->isNotEmpty()) {
                Villa::whereIn('id', $addIds)->update(['owner_id' => $user->id]);
            }
            if ($removeIds->isNotEmpty()) {
                Villa::whereIn('id', $removeIds)->update(['owner_id' => null]);
            }
        });

        if ($added->isNotEmpty()) {
            $names = $added->pluck('name')->map(fn ($n) => "\"{$n}\"")->implode(', ');
            $link = $added->count() === 1 ? "/owner/properties/{$added->first()->id}/edit" : '/owner/properties';
            NotificationService::owner($user->id, 'villa', 'Properti ditautkan ke akun Anda', "Properti {$names} kini dapat Anda kelola dari Extranet.", $link);
        }
        if ($removed->isNotEmpty()) {
            $names = $removed->pluck('name')->map(fn ($n) => "\"{$n}\"")->implode(', ');
            NotificationService::owner($user->id, 'villa', 'Properti dilepas dari akun Anda', "Properti {$names} tidak lagi terhubung ke akun Anda.", '/owner/properties');
        }
        foreach ($moved->groupBy('owner_id') as $prevOwnerId => $rows) {
            $names = $rows->pluck('name')->map(fn ($n) => "\"{$n}\"")->implode(', ');
            NotificationService::owner((int) $prevOwnerId, 'villa', 'Properti dipindahkan', "Properti {$names} dipindahkan ke akun owner lain oleh admin.", '/owner/properties');
        }
        if ($added->isNotEmpty() || $removed->isNotEmpty()) {
            NotificationService::admin('account', 'Properti owner diperbarui', "{$user->name}: {$added->count()} properti ditautkan, {$removed->count()} dilepas.", '/admin/owners');
        }

        return response()->json([
            'message' => "Properti {$user->name} diperbarui: {$added->count()} ditautkan, {$removed->count()} dilepas.",
            'added' => $added->count(),
            'removed' => $removed->count(),
            'moved' => $moved->count(),
            'villas_count' => $user->ownedVillas()->count(),
            'active_villas_count' => $user->ownedVillas()->where('is_active', true)->count(),
            'pending_villas_count' => $user->ownedVillas()->where('review_status', 'pending')->count(),
        ]);
    }

    private function ensureOwner(User $user): void
    {
        abort_unless($user->role === 'owner', 404, 'Akun owner tidak ditemukan.');
    }

    private function rules(?User $user = null): array
    {
        $id = $user?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            // Unik di antara akun owner saja — boleh sama dengan email customer/admin
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->where('role', 'owner')->ignore($id)],
            'username' => ['required', 'string', 'min:4', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Nama owner wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah dipakai akun owner lain.',
            'username.required' => 'Username wajib diisi.',
            'username.min' => 'Username minimal 4 karakter.',
            'username.regex' => 'Username hanya boleh huruf, angka, titik, garis bawah, dan strip (tanpa spasi).',
            'username.unique' => 'Username sudah dipakai akun lain.',
            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'password.min' => 'Password minimal 8 karakter.',
        ];
    }
}

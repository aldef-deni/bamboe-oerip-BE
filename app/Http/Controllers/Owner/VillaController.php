<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Concerns\ManagesVillas;
use App\Http\Controllers\Controller;
use App\Models\Villa;
use App\Models\VillaImage;
use App\Services\NotificationService;
use Illuminate\Http\Request;

/**
 * Properti milik owner. Properti baru / yang diajukan ulang berstatus
 * "pending" dan baru tampil di situs setelah disetujui superadmin.
 */
class VillaController extends Controller
{
    use ManagesVillas;

    public function index(Request $request)
    {
        $query = $request->user()->ownedVillas()
            ->with(['images', 'amenities:id,name'])
            ->withCount([
                'bookings',
                'bookings as active_bookings_count' => fn ($q) => $q->whereIn('status', ['pending', 'awaiting_verification', 'confirmed']),
            ]);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            match ($status) {
                'active' => $query->where('is_active', true),
                'inactive' => $query->where('is_active', false)->where('review_status', 'approved'),
                'pending', 'rejected' => $query->where('review_status', $status),
                default => null,
            };
        }

        return response()->json($query->latest()->get());
    }

    public function show(Request $request, Villa $villa)
    {
        $this->authorizeVilla($request, $villa);

        return response()->json($villa->load(['images', 'amenities']));
    }

    public function store(Request $request)
    {
        $data = $this->validatedVilla($request, false);
        $data['owner_id'] = $request->user()->id;
        $data['is_active'] = false;
        $data['is_featured'] = false;
        $data['review_status'] = 'pending';

        $villa = Villa::create($data);
        $this->syncAmenities($request, $villa);
        $this->handleImages($request, $villa);

        $owner = $request->user();
        NotificationService::admin('villa', 'Properti baru perlu ditinjau', "{$owner->name} mengajukan properti \"{$villa->name}\" ({$villa->city}).", '/admin/villas?review=pending');
        NotificationService::owner($owner->id, 'villa', 'Properti dikirim untuk ditinjau', "Properti \"{$villa->name}\" sedang ditinjau tim Bamboe Oerip. Anda akan diberi tahu setelah disetujui.", "/owner/properties/{$villa->id}/edit");

        return response()->json($villa->load(['images', 'amenities']), 201);
    }

    public function update(Request $request, Villa $villa)
    {
        $this->authorizeVilla($request, $villa);

        $data = $this->validatedVilla($request, false);
        $resubmitted = $villa->review_status === 'rejected';

        if ($resubmitted) {
            $data['review_status'] = 'pending';
        }

        // Owner hanya boleh membuka/menutup listing yang sudah disetujui
        if ($villa->review_status === 'approved' && $request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $villa->update($data);
        $this->syncAmenities($request, $villa);
        $this->handleImages($request, $villa);

        $owner = $request->user();
        if ($resubmitted) {
            NotificationService::admin('villa', 'Properti diajukan ulang', "{$owner->name} memperbaiki & mengajukan ulang \"{$villa->name}\".", '/admin/villas?review=pending');
        } else {
            NotificationService::admin('villa', 'Properti owner diperbarui', "{$owner->name} memperbarui properti \"{$villa->name}\".", '/admin/villas');
        }

        return response()->json($villa->fresh(['images', 'amenities']));
    }

    /** Buka/tutup listing (hanya properti yang sudah disetujui). */
    public function toggleActive(Request $request, Villa $villa)
    {
        $this->authorizeVilla($request, $villa);

        if ($villa->review_status !== 'approved') {
            return response()->json(['message' => 'Properti belum disetujui admin sehingga belum bisa diaktifkan.'], 422);
        }

        $villa->update(['is_active' => ! $villa->is_active]);

        $state = $villa->is_active ? 'diaktifkan (tampil di situs)' : 'dinonaktifkan (disembunyikan)';
        NotificationService::admin('villa', 'Status properti owner berubah', "{$request->user()->name}: \"{$villa->name}\" {$state}.", '/admin/villas');

        return response()->json($villa->fresh(['images', 'amenities']));
    }

    public function destroy(Request $request, Villa $villa)
    {
        $this->authorizeVilla($request, $villa);

        if ($reason = $this->villaDeleteBlocker($villa)) {
            return response()->json(['message' => $reason], 422);
        }

        $this->deleteVillaFiles($villa);
        $name = $villa->name;
        $villa->delete();

        NotificationService::admin('villa', 'Properti owner dihapus', "{$request->user()->name} menghapus properti \"{$name}\".", '/admin/villas');

        return response()->json(['message' => 'Properti dihapus.']);
    }

    /**
     * Unggah SATU foto per request — menghindari batas upload_max_filesize /
     * post_max_size server saat owner mengunggah banyak foto sekaligus.
     */
    public function uploadImage(Request $request, Villa $villa)
    {
        $this->authorizeVilla($request, $villa);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'primary' => ['nullable', 'boolean'],
        ], [
            'image.required' => 'Pilih foto yang akan diunggah.',
            'image.uploaded' => 'Foto gagal diunggah (ukuran file terlalu besar untuk server).',
            'image.image' => 'File harus berupa gambar.',
            'image.mimes' => 'Format foto harus JPG, PNG, atau WEBP.',
            'image.max' => 'Ukuran foto maksimal 8 MB.',
        ]);

        if ($villa->images()->count() >= 40) {
            return response()->json(['message' => 'Maksimal 40 foto per properti.'], 422);
        }

        $image = $this->storeVillaImage($villa, $request->file('image'), $request->boolean('primary'));

        return response()->json($image, 201);
    }

    public function deleteImage(Request $request, Villa $villa, VillaImage $image)
    {
        $this->authorizeVilla($request, $villa);
        $this->removeVillaImage($villa, $image);

        return response()->json(['message' => 'Foto dihapus.']);
    }

    public function setPrimaryImage(Request $request, Villa $villa, VillaImage $image)
    {
        $this->authorizeVilla($request, $villa);
        $this->makePrimaryImage($villa, $image);

        return response()->json(['message' => 'Foto utama diperbarui.']);
    }

    /** Owner hanya boleh mengakses properti miliknya sendiri (404 agar tak bocor). */
    private function authorizeVilla(Request $request, Villa $villa): void
    {
        abort_unless((int) $villa->owner_id === (int) $request->user()->id, 404, 'Properti tidak ditemukan.');
    }
}

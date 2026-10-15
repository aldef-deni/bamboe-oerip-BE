<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ManagesVillas;
use App\Http\Controllers\Controller;
use App\Models\Villa;
use App\Models\VillaImage;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VillaController extends Controller
{
    use ManagesVillas;

    public function index(Request $request)
    {
        $query = Villa::withCount('bookings')->with(['images', 'owner:id,name,email']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($review = $request->query('review')) {
            $query->where('review_status', $review);
        }

        if ($ownerId = $request->query('owner_id')) {
            $query->where('owner_id', $ownerId);
        }

        return response()->json($query->latest()->paginate((int) $request->query('per_page', 15)));
    }

    public function show(Villa $villa)
    {
        $villa->load(['images', 'amenities', 'owner:id,name,email']);

        return response()->json($villa);
    }

    public function store(Request $request)
    {
        $data = $this->validatedVilla($request);
        $data['owner_id'] = $this->validatedOwnerId($request);
        $data['review_status'] = 'approved';

        $villa = Villa::create($data);
        $this->syncAmenities($request, $villa);
        $this->handleImages($request, $villa);

        NotificationService::admin('villa', 'Villa ditambahkan', "Villa \"{$villa->name}\" ditambahkan.", '/admin/villas');
        NotificationService::villaOwner($villa, 'villa', 'Properti baru ditambahkan', "Admin menambahkan properti \"{$villa->name}\" ke akun Anda.", "/owner/properties/{$villa->id}/edit");

        $villa->load(['images', 'amenities', 'owner:id,name,email']);

        return response()->json($villa, 201);
    }

    public function update(Request $request, Villa $villa)
    {
        $data = $this->validatedVilla($request);
        if ($request->has('owner_id')) {
            $data['owner_id'] = $this->validatedOwnerId($request);
        }

        // Admin mengaktifkan villa yang masih ditinjau -> dianggap disetujui
        if (! empty($data['is_active']) && $villa->review_status !== 'approved') {
            $data['review_status'] = 'approved';
            $data['review_note'] = null;
        }

        $previousOwner = $villa->owner_id;
        $villa->update($data);
        $this->syncAmenities($request, $villa);
        $this->handleImages($request, $villa);

        NotificationService::admin('villa', 'Villa diperbarui', "Villa \"{$villa->name}\" diperbarui.", '/admin/villas');

        if ($villa->owner_id && $villa->owner_id !== $previousOwner) {
            NotificationService::owner($villa->owner_id, 'villa', 'Properti ditautkan ke akun Anda', "Properti \"{$villa->name}\" kini dikelola melalui akun Anda.", "/owner/properties/{$villa->id}/edit");
        } else {
            NotificationService::villaOwner($villa, 'villa', 'Properti diperbarui admin', "Data properti \"{$villa->name}\" diperbarui oleh admin Bamboe Oerip.", "/owner/properties/{$villa->id}/edit");
        }

        $villa->load(['images', 'amenities', 'owner:id,name,email']);

        return response()->json($villa);
    }

    public function destroy(Villa $villa)
    {
        if ($reason = $this->villaDeleteBlocker($villa)) {
            return response()->json(['message' => $reason], 422);
        }

        $this->deleteVillaFiles($villa);

        $name = $villa->name;
        $ownerId = $villa->owner_id;
        $villa->delete();

        NotificationService::admin('villa', 'Villa dihapus', "Villa \"{$name}\" dihapus.", '/admin/villas');
        NotificationService::owner($ownerId, 'villa', 'Properti dihapus', "Properti \"{$name}\" dihapus oleh admin Bamboe Oerip.", '/owner/properties');

        return response()->json(['message' => 'Villa dihapus.']);
    }

    /** Setujui properti ajuan owner -> tampil di situs. */
    public function approve(Villa $villa)
    {
        $villa->update(['review_status' => 'approved', 'review_note' => null, 'is_active' => true]);

        NotificationService::villaOwner($villa, 'villa', 'Properti disetujui', "Selamat! Properti \"{$villa->name}\" telah disetujui dan kini tampil di Bamboe Oerip.", "/owner/properties/{$villa->id}/edit");

        return response()->json($villa->load(['images', 'owner:id,name,email'])->loadCount('bookings'));
    }

    /** Tolak properti ajuan owner (dengan catatan perbaikan). */
    public function reject(Request $request, Villa $villa)
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
        ], [
            'note.required' => 'Tuliskan alasan / catatan perbaikan untuk owner.',
        ]);

        $villa->update(['review_status' => 'rejected', 'review_note' => $data['note'], 'is_active' => false]);

        NotificationService::villaOwner($villa, 'villa', 'Properti perlu diperbaiki', "Properti \"{$villa->name}\" belum disetujui. Catatan: {$data['note']}", "/owner/properties/{$villa->id}/edit");

        return response()->json($villa->load(['images', 'owner:id,name,email'])->loadCount('bookings'));
    }

    public function deleteImage(Villa $villa, VillaImage $image)
    {
        $this->removeVillaImage($villa, $image);

        return response()->json(['message' => 'Foto dihapus.']);
    }

    public function setPrimaryImage(Villa $villa, VillaImage $image)
    {
        $this->makePrimaryImage($villa, $image);

        return response()->json(['message' => 'Foto utama diperbarui.']);
    }

    /** owner_id opsional; harus akun ber-role owner. Kosong = milik Bamboe Oerip. */
    private function validatedOwnerId(Request $request): ?int
    {
        $data = $request->validate([
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'owner')],
        ], [
            'owner_id.exists' => 'Owner yang dipilih tidak ditemukan.',
        ]);

        return ! empty($data['owner_id']) ? (int) $data['owner_id'] : null;
    }
}

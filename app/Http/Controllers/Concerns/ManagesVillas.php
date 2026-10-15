<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Amenity;
use App\Models\Villa;
use App\Models\VillaImage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Logika villa yang dipakai bersama panel admin & ekstranet owner:
 * validasi data, sinkron fasilitas, unggah/hapus foto, dan cek hapus aman.
 */
trait ManagesVillas
{
    /** Aturan validasi data villa. Flag publikasi (aktif/unggulan) hanya untuk admin. */
    protected function villaRules(bool $withPublishFlags = true): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:10000'],
            'city' => ['required', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'village' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'price_per_night' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'weekend_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'max_guests' => ['required', 'integer', 'min:1', 'max:1000'],
            'bedrooms' => ['required', 'integer', 'min:0', 'max:1000'],
            'bathrooms' => ['required', 'integer', 'min:0', 'max:1000'],
            'size_sqm' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'images' => ['nullable', 'array', 'max:30'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ];

        if ($withPublishFlags) {
            $rules['is_active'] = ['boolean'];
            $rules['is_featured'] = ['boolean'];
        }

        return $rules;
    }

    protected function villaMessages(): array
    {
        return [
            'name.required' => 'Nama properti wajib diisi.',
            'city.required' => 'Kota/Kabupaten wajib dipilih.',
            'price_per_night.required' => 'Harga per malam wajib diisi.',
            'price_per_night.numeric' => 'Harga per malam harus berupa angka.',
            'weekend_price.numeric' => 'Harga weekend harus berupa angka.',
            'max_guests.required' => 'Maksimal tamu wajib diisi.',
            'max_guests.min' => 'Maksimal tamu minimal 1 orang.',
            'bedrooms.required' => 'Jumlah kamar tidur wajib diisi.',
            'bathrooms.required' => 'Jumlah kamar mandi wajib diisi.',
            'latitude.between' => 'Latitude tidak valid (-90 s/d 90).',
            'longitude.between' => 'Longitude tidak valid (-180 s/d 180).',
            'images.max' => 'Maksimal 30 foto sekali unggah.',
            'images.*.image' => 'File harus berupa gambar.',
            'images.*.mimes' => 'Format foto harus JPG, PNG, atau WEBP.',
            'images.*.max' => 'Ukuran foto maksimal 8 MB.',
        ];
    }

    /** Data villa tervalidasi (tanpa field foto). */
    protected function validatedVilla(Request $request, bool $withPublishFlags = true): array
    {
        $data = $request->validate($this->villaRules($withPublishFlags), $this->villaMessages());
        unset($data['images']);

        return $data;
    }

    /**
     * Sinkron fasilitas. Menerima array id atau string "1,2,3".
     * Id yang tidak ada di tabel amenities diabaikan (hindari error foreign key).
     */
    protected function syncAmenities(Request $request, Villa $villa): void
    {
        if (! $request->has('amenities')) {
            return;
        }

        $ids = $request->input('amenities');
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        $ids = collect((array) $ids)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $valid = $ids->isEmpty()
            ? []
            : Amenity::whereIn('id', $ids)->pluck('id')->all();

        $villa->amenities()->sync($valid);
    }

    /** Simpan satu foto. Foto pertama otomatis jadi foto utama. */
    protected function storeVillaImage(Villa $villa, UploadedFile $file, bool $makePrimary = false): VillaImage
    {
        $path = $file->store('villas', 'public');
        $hasPrimary = $villa->images()->where('is_primary', true)->exists();
        $order = (int) $villa->images()->max('sort_order');

        if ($makePrimary && $hasPrimary) {
            $villa->images()->update(['is_primary' => false]);
        }

        return $villa->images()->create([
            'path' => $path,
            'is_primary' => $makePrimary || ! $hasPrimary,
            'sort_order' => $order + 1,
        ]);
    }

    /** Unggah banyak foto dari field images[] (+ primary_index opsional). */
    protected function handleImages(Request $request, Villa $villa): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        $primaryIndex = $request->filled('primary_index') ? (int) $request->input('primary_index') : null;

        foreach (array_values($request->file('images')) as $i => $file) {
            $this->storeVillaImage($villa, $file, $primaryIndex === $i);
        }
    }

    protected function removeVillaImage(Villa $villa, VillaImage $image): void
    {
        abort_unless((int) $image->villa_id === (int) $villa->id, 404);

        $wasPrimary = $image->is_primary;
        Storage::disk('public')->delete($image->path);
        $image->delete();

        // Jaga agar villa tetap punya foto utama
        if ($wasPrimary) {
            $villa->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
        }
    }

    protected function makePrimaryImage(Villa $villa, VillaImage $image): void
    {
        abort_unless((int) $image->villa_id === (int) $villa->id, 404);

        $villa->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);
    }

    /**
     * Alasan villa tidak boleh dihapus (null = boleh).
     * Tabel bookings memakai restrictOnDelete, jadi villa yang punya riwayat
     * pesanan apa pun tidak bisa dihapus — cukup dinonaktifkan.
     */
    protected function villaDeleteBlocker(Villa $villa): ?string
    {
        if ($villa->bookings()->whereIn('status', ['pending', 'awaiting_verification', 'confirmed'])->exists()) {
            return 'Tidak bisa menghapus properti yang masih memiliki pesanan aktif.';
        }

        if ($villa->bookings()->exists()) {
            return 'Properti ini memiliki riwayat pesanan sehingga tidak dapat dihapus. Nonaktifkan saja agar tidak tampil di situs.';
        }

        return null;
    }

    protected function deleteVillaFiles(Villa $villa): void
    {
        foreach ($villa->images as $image) {
            Storage::disk('public')->delete($image->path);
        }
    }
}

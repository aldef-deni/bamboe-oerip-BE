<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Villa;
use App\Models\VillaImage;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VillaController extends Controller
{
    public function index(Request $request)
    {
        $query = Villa::withCount('bookings')->with('images');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%");
        }

        return response()->json($query->latest()->paginate((int) $request->query('per_page', 15)));
    }

    public function show(Villa $villa)
    {
        $villa->load(['images', 'amenities']);

        return response()->json($villa);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $villa = Villa::create($data);
        $this->syncAmenities($request, $villa);
        $this->handleImages($request, $villa);

        NotificationService::admin('villa', 'Villa ditambahkan', "Villa \"{$villa->name}\" ditambahkan.", '/admin/villas');

        $villa->load(['images', 'amenities']);

        return response()->json($villa, 201);
    }

    public function update(Request $request, Villa $villa)
    {
        $data = $this->validateData($request, $villa);

        $villa->update($data);
        $this->syncAmenities($request, $villa);
        $this->handleImages($request, $villa);

        NotificationService::admin('villa', 'Villa diperbarui', "Villa \"{$villa->name}\" diperbarui.", '/admin/villas');

        $villa->load(['images', 'amenities']);

        return response()->json($villa);
    }

    public function destroy(Villa $villa)
    {
        if ($villa->bookings()->whereIn('status', ['pending', 'awaiting_verification', 'confirmed'])->exists()) {
            return response()->json(['message' => 'Tidak bisa menghapus villa dengan pesanan aktif.'], 422);
        }

        foreach ($villa->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        $name = $villa->name;
        $villa->delete();

        NotificationService::admin('villa', 'Villa dihapus', "Villa \"{$name}\" dihapus.", '/admin/villas');

        return response()->json(['message' => 'Villa dihapus.']);
    }

    public function deleteImage(Villa $villa, VillaImage $image)
    {
        abort_unless((int) $image->villa_id === (int) $villa->id, 404);

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return response()->json(['message' => 'Foto dihapus.']);
    }

    public function setPrimaryImage(Villa $villa, VillaImage $image)
    {
        abort_unless((int) $image->villa_id === (int) $villa->id, 404);

        $villa->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return response()->json(['message' => 'Foto utama diperbarui.']);
    }

    private function validateData(Request $request, ?Villa $villa = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'city' => ['required', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'village' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'price_per_night' => ['required', 'numeric', 'min:0'],
            'weekend_price' => ['nullable', 'numeric', 'min:0'],
            'max_guests' => ['required', 'integer', 'min:1'],
            'bedrooms' => ['required', 'integer', 'min:0'],
            'bathrooms' => ['required', 'integer', 'min:0'],
            'size_sqm' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
        ]);
    }

    private function syncAmenities(Request $request, Villa $villa): void
    {
        if ($request->has('amenities')) {
            $ids = $request->input('amenities');
            if (is_string($ids)) {
                $ids = array_filter(explode(',', $ids));
            }
            $villa->amenities()->sync($ids ?: []);
        }
    }

    private function handleImages(Request $request, Villa $villa): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        $primaryIndex = $request->filled('primary_index') ? (int) $request->input('primary_index') : null;
        $hasPrimary = $villa->images()->where('is_primary', true)->exists();
        $order = (int) $villa->images()->max('sort_order');

        $created = [];
        foreach ($request->file('images') as $file) {
            $path = $file->store('villas', 'public');
            // Tanpa pilihan admin: foto pertama jadi utama hanya bila belum ada foto utama
            $isPrimary = $primaryIndex === null && ! $hasPrimary;
            $created[] = $villa->images()->create([
                'path' => $path,
                'is_primary' => $isPrimary,
                'sort_order' => ++$order,
            ]);
            if ($isPrimary) {
                $hasPrimary = true;
            }
        }

        // Admin memilih salah satu foto baru sebagai utama -> jadikan satu-satunya utama
        if ($primaryIndex !== null && isset($created[$primaryIndex])) {
            $villa->images()->update(['is_primary' => false]);
            $created[$primaryIndex]->update(['is_primary' => true]);
        }
    }
}

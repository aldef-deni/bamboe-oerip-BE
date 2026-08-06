<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Villa;
use Illuminate\Http\Request;

class VillaController extends Controller
{
    public function index(Request $request)
    {
        $query = Villa::query()
            ->with(['images', 'amenities'])
            ->where('is_active', true);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('province', 'like', "%{$search}%");
            });
        }

        if ($city = $request->query('city')) {
            $query->where('city', $city);
        }

        if ($guests = $request->query('guests')) {
            $query->where('max_guests', '>=', (int) $guests);
        }

        if ($min = $request->query('min_price')) {
            $query->where('price_per_night', '>=', (float) $min);
        }

        if ($max = $request->query('max_price')) {
            $query->where('price_per_night', '<=', (float) $max);
        }

        if ($request->filled('amenities')) {
            $ids = array_filter(array_map('intval', explode(',', $request->query('amenities'))));
            foreach ($ids as $id) {
                $query->whereHas('amenities', fn ($q) => $q->where('amenities.id', $id));
            }
        }

        switch ($request->query('sort')) {
            case 'price_asc':
                $query->orderBy('price_per_night', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price_per_night', 'desc');
                break;
            case 'rating':
                $query->orderBy('rating_avg', 'desc');
                break;
            default:
                $query->orderByDesc('is_featured')->orderByDesc('created_at');
        }

        // Optional availability filter by date range
        $checkIn = $request->query('check_in');
        $checkOut = $request->query('check_out');
        if ($checkIn && $checkOut) {
            $query->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut) {
                $q->whereIn('status', Booking::ACTIVE_STATUSES)
                    ->where('check_in', '<', $checkOut)
                    ->where('check_out', '>', $checkIn);
            });
        }

        $villas = $query->paginate((int) $request->query('per_page', 12));

        return response()->json($villas);
    }

    public function show(Villa $villa)
    {
        abort_unless($villa->is_active, 404);

        $villa->load(['images', 'amenities', 'reviews.user:id,name,avatar']);

        return response()->json($villa);
    }

    /**
     * Titik lokasi villa untuk peta sebaran.
     * Hanya akomodasi UNGGULAN (is_featured) yang punya koordinat.
     */
    public function mapPoints()
    {
        $villas = Villa::with('images')
            ->where('is_active', true)
            ->where('is_featured', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('province')
            ->orderBy('city')
            ->get();

        return response()->json(
            $villas->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'slug' => $v->slug,
                'type' => $v->type,
                'city' => $v->city,
                'province' => $v->province ?: 'Lainnya',
                'lat' => (float) $v->latitude,
                'lng' => (float) $v->longitude,
                'price' => (float) $v->price_per_night,
                'image' => $v->primary_image_url,
            ])->values()
        );
    }

    public function cities()
    {
        $cities = Villa::where('is_active', true)
            ->select('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city');

        return response()->json($cities);
    }

    public function amenities()
    {
        return response()->json(Amenity::orderBy('name')->get());
    }

    public function availability(Request $request, Villa $villa)
    {
        $data = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
        ]);

        $overlap = $villa->bookings()
            ->whereIn('status', Booking::ACTIVE_STATUSES)
            ->where('check_in', '<', $data['check_out'])
            ->where('check_out', '>', $data['check_in'])
            ->exists();

        return response()->json(['available' => ! $overlap]);
    }
}

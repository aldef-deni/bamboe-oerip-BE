<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

/** Ulasan tamu untuk properti milik owner. */
class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $villaIds = $request->user()->ownedVillas()->pluck('id');

        $query = Review::whereIn('villa_id', $villaIds)
            ->with(['user:id,name,avatar,google_avatar', 'villa:id,name']);

        if ($villaId = $request->query('villa_id')) {
            $query->where('villa_id', $villaId);
        }

        if ($rating = $request->query('rating')) {
            $query->where('rating', (int) $rating);
        }

        $distribution = Review::whereIn('villa_id', $villaIds)
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        return response()->json([
            'reviews' => $query->latest()->paginate(min(50, (int) $request->query('per_page', 15))),
            'distribution' => $distribution,
            'average' => round((float) Review::whereIn('villa_id', $villaIds)->avg('rating'), 2),
            'total' => (int) $distribution->sum(),
        ]);
    }
}

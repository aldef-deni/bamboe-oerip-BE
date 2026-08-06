<?php

namespace App\Http\Controllers;

use App\Models\Villa;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $villas = Villa::whereIn('id', $request->user()->wishlists()->pluck('villa_id'))
            ->with(['images', 'amenities'])
            ->where('is_active', true)
            ->get();

        return response()->json($villas);
    }

    public function toggle(Request $request, Villa $villa)
    {
        $user = $request->user();

        $existing = Wishlist::where('user_id', $user->id)
            ->where('villa_id', $villa->id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['wishlisted' => false]);
        }

        Wishlist::create([
            'user_id' => $user->id,
            'villa_id' => $villa->id,
        ]);

        return response()->json(['wishlisted' => true]);
    }
}

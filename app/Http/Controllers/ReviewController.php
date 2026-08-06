<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Review;
use App\Models\Villa;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function index(Villa $villa)
    {
        $reviews = $villa->reviews()
            ->with('user:id,name,avatar')
            ->latest()
            ->paginate(10);

        return response()->json($reviews);
    }

    public function store(Request $request, Villa $villa)
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        // User must have a completed/confirmed booking for this villa
        $eligibleBooking = Booking::where('user_id', $user->id)
            ->where('villa_id', $villa->id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->latest()
            ->first();

        if (! $eligibleBooking) {
            throw ValidationException::withMessages([
                'rating' => ['Anda hanya bisa mereview villa yang sudah Anda pesan.'],
            ]);
        }

        if (Review::where('user_id', $user->id)->where('villa_id', $villa->id)->exists()) {
            throw ValidationException::withMessages([
                'rating' => ['Anda sudah pernah mereview villa ini.'],
            ]);
        }

        $review = Review::create([
            'user_id' => $user->id,
            'villa_id' => $villa->id,
            'booking_id' => $eligibleBooking->id,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
        ]);

        $villa->recalculateRating();

        $review->load('user:id,name,avatar');

        return response()->json($review, 201);
    }
}

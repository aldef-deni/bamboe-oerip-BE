<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Booking::with(['villa:id,name,city', 'user:id,name,email', 'payment']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('booking_code', 'like', "%{$search}%")
                    ->orWhere('guest_name', 'like', "%{$search}%")
                    ->orWhere('guest_phone', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest()->paginate((int) $request->query('per_page', 15)));
    }

    public function show(Booking $booking)
    {
        $booking->load(['villa.images', 'user:id,name,email,phone', 'payment']);

        return response()->json($booking);
    }

    public function updateStatus(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,awaiting_verification,confirmed,completed,cancelled,rejected'],
        ]);

        $booking->update(['status' => $data['status']]);

        // Berikan poin loyalitas saat pesanan dikonfirmasi/selesai (idempoten)
        if (in_array($data['status'], ['confirmed', 'completed'], true)) {
            LoyaltyService::awardForBooking($booking);
        }

        $booking->load(['villa:id,name', 'user:id,name', 'payment']);

        return response()->json($booking);
    }

    public function destroy(Booking $booking)
    {
        if ($booking->payment?->proof_path) {
            Storage::disk('public')->delete($booking->payment->proof_path);
        }
        $booking->delete(); // payment ikut terhapus (cascade)

        return response()->json(['message' => 'Pesanan dihapus permanen.']);
    }

    /**
     * Hapus permanen beberapa pesanan sekaligus.
     */
    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $bookings = Booking::whereIn('id', $data['ids'])->with('payment')->get();
        foreach ($bookings as $b) {
            if ($b->payment?->proof_path) {
                Storage::disk('public')->delete($b->payment->proof_path);
            }
        }

        $count = Booking::whereIn('id', $data['ids'])->delete();

        return response()->json(['message' => "{$count} pesanan dihapus permanen.", 'deleted' => $count]);
    }
}

<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;

/**
 * Pesanan pada properti milik owner (baca saja). Verifikasi pembayaran &
 * perubahan status tetap dilakukan superadmin di panel kelola.
 */
class BookingController extends Controller
{
    public function index(Request $request)
    {
        $villaIds = $request->user()->ownedVillas()->pluck('id');

        $query = Booking::whereIn('villa_id', $villaIds)
            ->with(['villa:id,name,city', 'payment:id,booking_id,status,paid_at,verified_at']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($villaId = $request->query('villa_id')) {
            $query->where('villa_id', $villaId);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('booking_code', 'like', "%{$search}%")
                    ->orWhere('guest_name', 'like', "%{$search}%");
            });
        }

        // Rentang tanggal menginap (berdasarkan check-in)
        if ($from = $request->query('from')) {
            $query->whereDate('check_in', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('check_in', '<=', $to);
        }

        $sort = $request->query('sort') === 'check_in' ? 'check_in' : 'created_at';
        $query->orderBy($sort, $sort === 'check_in' ? 'asc' : 'desc');

        $page = $query->paginate(min(100, (int) $request->query('per_page', 15)));
        $page->getCollection()->each(fn (Booking $b) => $b->makeHidden(['guest_phone', 'guest_email', 'user_id']));

        return response()->json($page);
    }

    public function show(Request $request, Booking $booking)
    {
        $booking->load(['villa:id,name,city,address,owner_id', 'villa.images', 'payment:id,booking_id,status,paid_at,verified_at']);

        abort_unless($booking->villa && (int) $booking->villa->owner_id === (int) $request->user()->id, 404, 'Pesanan tidak ditemukan.');

        // Kontak tamu hanya dibuka setelah pesanan terbayar/terkonfirmasi
        $booking->makeHidden('user_id');
        if (! in_array($booking->status, ['confirmed', 'completed'], true)) {
            $booking->makeHidden(['guest_phone', 'guest_email']);
        }

        return response()->json($booking);
    }
}

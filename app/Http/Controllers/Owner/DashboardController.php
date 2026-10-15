<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Ringkasan ekstranet owner. Semua angka hanya dari properti milik owner.
 * "Pendapatan" = nilai sewa (subtotal) pesanan terbayar, di luar biaya
 * layanan & pajak yang menjadi bagian platform.
 */
class DashboardController extends Controller
{
    private const PAID = ['confirmed', 'completed'];

    public function index(Request $request)
    {
        $owner = $request->user();
        $villaIds = $owner->ownedVillas()->pluck('id');
        $today = now()->startOfDay();

        $villas = $owner->ownedVillas()->get(['id', 'name', 'is_active', 'review_status', 'rating_avg', 'review_count']);
        $bookings = fn () => Booking::whereIn('villa_id', $villaIds);

        // Okupansi bulan ini: malam terpesan / (properti aktif x hari dalam bulan)
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth()->startOfDay()->addDay();
        $nightsBooked = $bookings()
            ->whereIn('status', self::PAID)
            ->where('check_in', '<', $monthEnd)
            ->where('check_out', '>', $monthStart)
            ->get(['check_in', 'check_out'])
            ->sum(function ($b) use ($monthStart, $monthEnd) {
                $from = $b->check_in->max($monthStart);
                $to = $b->check_out->min($monthEnd);

                return max(0, (int) $from->diffInDays($to));
            });
        $activeCount = $villas->where('is_active', true)->count();
        $capacity = $activeCount * $monthStart->daysInMonth;

        $reviewCount = (int) $villas->sum('review_count');
        $ratingAvg = $reviewCount > 0
            ? round($villas->sum(fn ($v) => (float) $v->rating_avg * $v->review_count) / $reviewCount, 2)
            : 0;

        return response()->json([
            'stats' => [
                'properties' => $villas->count(),
                'active_properties' => $activeCount,
                'pending_properties' => $villas->where('review_status', 'pending')->count(),
                'rejected_properties' => $villas->where('review_status', 'rejected')->count(),
                'bookings' => $bookings()->count(),
                'awaiting_payment' => $bookings()->whereIn('status', ['pending', 'awaiting_verification'])->count(),
                'upcoming' => $bookings()->where('status', 'confirmed')->whereDate('check_in', '>=', $today)->count(),
                'in_house' => $bookings()->whereIn('status', self::PAID)
                    ->whereDate('check_in', '<=', $today)->whereDate('check_out', '>', $today)->count(),
                'revenue_total' => (float) $bookings()->whereIn('status', self::PAID)->sum('subtotal'),
                'revenue_month' => (float) $bookings()->whereIn('status', self::PAID)
                    ->whereBetween('check_in', [$monthStart->toDateString(), now()->endOfMonth()->toDateString()])->sum('subtotal'),
                'nights_month' => $nightsBooked,
                'occupancy_month' => $capacity > 0 ? round($nightsBooked / $capacity * 100, 1) : 0,
                'rating_avg' => $ratingAvg,
                'review_count' => $reviewCount,
            ],
            'monthly' => $this->monthly($villaIds),
            'upcoming_checkins' => $bookings()
                ->with('villa:id,name')
                ->where('status', 'confirmed')
                ->whereDate('check_in', '>=', $today)
                ->orderBy('check_in')
                ->limit(6)
                ->get(['id', 'booking_code', 'villa_id', 'guest_name', 'guests', 'check_in', 'check_out', 'nights', 'status']),
            'recent_bookings' => $bookings()
                ->with('villa:id,name')
                ->latest()
                ->limit(6)
                ->get(['id', 'booking_code', 'villa_id', 'guest_name', 'check_in', 'check_out', 'nights', 'subtotal', 'status', 'created_at']),
            'top_properties' => $owner->ownedVillas()
                ->with('images')
                ->withCount(['bookings as paid_bookings' => fn ($q) => $q->whereIn('status', self::PAID)])
                ->withSum(['bookings as revenue' => fn ($q) => $q->whereIn('status', self::PAID)], 'subtotal')
                ->orderByDesc('revenue')
                ->limit(5)
                ->get(['id', 'name', 'city', 'type', 'is_active', 'review_status', 'rating_avg', 'review_count']),
        ]);
    }

    /** Pendapatan & jumlah pesanan 6 bulan terakhir berdasarkan bulan check-in. */
    private function monthly($villaIds): array
    {
        $start = now()->subMonths(5)->startOfMonth();
        $rows = Booking::whereIn('villa_id', $villaIds)
            ->whereIn('status', self::PAID)
            ->whereDate('check_in', '>=', $start)
            ->whereDate('check_in', '<=', now()->endOfMonth())
            ->get(['check_in', 'subtotal']);

        $result = [];
        for ($i = 0; $i < 6; $i++) {
            $m = $start->copy()->addMonths($i);
            $key = $m->format('Y-m');
            $inMonth = $rows->filter(fn ($b) => $b->check_in->format('Y-m') === $key);
            $result[] = [
                'month' => $key,
                'revenue' => (float) $inMonth->sum('subtotal'),
                'bookings' => $inMonth->count(),
            ];
        }

        return $result;
    }
}

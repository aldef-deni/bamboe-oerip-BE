<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;

/**
 * Laporan pendapatan owner. Hanya pesanan terbayar (confirmed/completed),
 * difilter berdasarkan tanggal check-in. Nilai yang menjadi hak owner =
 * subtotal (harga sewa x malam), di luar biaya layanan & pajak platform.
 */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'villa_id' => ['nullable', 'integer'],
        ]);

        $villaIds = $request->user()->ownedVillas()->pluck('id');

        $base = fn () => Booking::whereIn('villa_id', $villaIds)
            ->whereIn('status', ['confirmed', 'completed'])
            ->when(! empty($data['villa_id']), fn ($q) => $q->where('villa_id', $data['villa_id']))
            ->when(! empty($data['from']), fn ($q) => $q->whereDate('check_in', '>=', $data['from']))
            ->when(! empty($data['to']), fn ($q) => $q->whereDate('check_in', '<=', $data['to']));

        $summary = [
            'count' => $base()->count(),
            'nights' => (int) $base()->sum('nights'),
            'guests' => (int) $base()->sum('guests'),
            'revenue' => (float) $base()->sum('subtotal'),
        ];
        $summary['adr'] = $summary['nights'] > 0 ? round($summary['revenue'] / $summary['nights']) : 0; // rata-rata harga per malam

        $perProperty = $base()
            ->with('villa:id,name')
            ->get(['villa_id', 'nights', 'subtotal'])
            ->groupBy('villa_id')
            ->map(fn ($rows) => [
                'villa_id' => $rows->first()->villa_id,
                'villa' => $rows->first()->villa?->name,
                'bookings' => $rows->count(),
                'nights' => (int) $rows->sum('nights'),
                'revenue' => (float) $rows->sum('subtotal'),
            ])
            ->sortByDesc('revenue')
            ->values();

        $transactions = $base()
            ->with('villa:id,name')
            ->orderByDesc('check_in')
            ->limit(500)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'booking_code' => $b->booking_code,
                'villa' => $b->villa?->name,
                'guest_name' => $b->guest_name,
                'guests' => $b->guests,
                'check_in' => $b->check_in?->toDateString(),
                'check_out' => $b->check_out?->toDateString(),
                'nights' => $b->nights,
                'price_per_night' => (float) $b->price_per_night,
                'revenue' => (float) $b->subtotal,
                'status' => $b->status,
            ]);

        return response()->json([
            'summary' => $summary,
            'per_property' => $perProperty,
            'transactions' => $transactions,
        ]);
    }
}

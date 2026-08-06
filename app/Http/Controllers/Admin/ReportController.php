<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Laporan transaksi & pendapatan. Hanya booking yang sudah dibayar
     * (confirmed/completed) yang dihitung sebagai transaksi.
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $base = fn () => Booking::whereIn('status', ['confirmed', 'completed'])
            ->when(! empty($data['from']), fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
            ->when(! empty($data['to']), fn ($q) => $q->whereDate('created_at', '<=', $data['to']));

        $summary = [
            'count' => $base()->count(),
            'subtotal' => (float) $base()->sum('subtotal'),    // nilai sewa (untuk pemilik)
            'discount' => (float) $base()->sum('discount'),    // total potongan promo
            'service_fee' => (float) $base()->sum('service_fee'), // KEUNTUNGAN (biaya layanan)
            'total' => (float) $base()->sum('total'),          // NILAI TRANSAKSI (dibayar customer)
        ];

        $transactions = $base()
            ->with(['villa:id,name,city', 'user:id,name'])
            ->latest()
            ->limit(500)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'booking_code' => $b->booking_code,
                'villa' => $b->villa?->name,
                'city' => $b->villa?->city,
                'customer' => $b->user?->name,
                'guest_name' => $b->guest_name,
                'check_in' => $b->check_in,
                'check_out' => $b->check_out,
                'nights' => $b->nights,
                'subtotal' => $b->subtotal,
                'discount' => $b->discount,
                'service_fee' => $b->service_fee,
                'total' => $b->total,
                'promo_code' => $b->promo_code,
                'status' => $b->status,
                'created_at' => $b->created_at,
            ]);

        return response()->json([
            'summary' => $summary,
            'transactions' => $transactions,
        ]);
    }
}

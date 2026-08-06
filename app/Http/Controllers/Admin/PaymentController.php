<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    /**
     * Hapus permanen beberapa data pembayaran sekaligus (+ file bukti transfer).
     */
    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $payments = Payment::whereIn('id', $data['ids'])->get();
        foreach ($payments as $p) {
            if ($p->proof_path) {
                Storage::disk('public')->delete($p->proof_path);
            }
        }

        $count = Payment::whereIn('id', $data['ids'])->delete();

        return response()->json(['message' => "{$count} data pembayaran dihapus permanen.", 'deleted' => $count]);
    }

    public function index(Request $request)
    {
        $query = Payment::with(['booking.villa:id,name', 'booking.user:id,name']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->latest()->paginate((int) $request->query('per_page', 15)));
    }

    public function verify(Request $request, Payment $payment)
    {
        $payment->update([
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);

        $payment->booking->update(['status' => 'confirmed']);

        // Berikan poin loyalitas (idempoten)
        LoyaltyService::awardForBooking($payment->booking);

        $payment->load(['booking.villa:id,name', 'booking.user:id,name']);

        return response()->json($payment);
    }

    public function reject(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $payment->update([
            'status' => 'rejected',
            'note' => $data['note'] ?? null,
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);

        $payment->booking->update(['status' => 'rejected']);

        $payment->load(['booking.villa:id,name', 'booking.user:id,name']);

        return response()->json($payment);
    }
}

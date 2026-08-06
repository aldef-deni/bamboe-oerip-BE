<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\VoucherMail;
use App\Models\Payment;
use App\Services\LoyaltyService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        NotificationService::admin('payment', 'Pembayaran dihapus', "{$count} data pembayaran dihapus permanen.", '/admin/payments');

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

        NotificationService::user($payment->booking->user_id, 'payment', 'Pembayaran terverifikasi', "Pembayaran untuk {$payment->booking->booking_code} telah diverifikasi. Pesanan Anda dikonfirmasi.", "/bookings/{$payment->booking->id}");

        // Kirim e-voucher ke email pelanggan
        try {
            $booking = $payment->booking->load('villa');
            $recipient = $booking->guest_email ?: $booking->user?->email;
            if ($recipient) {
                Mail::to($recipient)->send(new VoucherMail($booking));
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim e-voucher: ' . $e->getMessage());
        }

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

        $note = ! empty($data['note']) ? ' Alasan: ' . $data['note'] : '';
        NotificationService::user($payment->booking->user_id, 'payment', 'Pembayaran ditolak', "Pembayaran untuk {$payment->booking->booking_code} ditolak.{$note}", "/bookings/{$payment->booking->id}");

        $payment->load(['booking.villa:id,name', 'booking.user:id,name']);

        return response()->json($payment);
    }
}

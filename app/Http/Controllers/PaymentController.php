<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    /**
     * Public bank/transfer instructions.
     */
    public function bankInfo()
    {
        return response()->json([
            'bank_name' => Setting::get('bank_name'),
            'bank_account_number' => Setting::get('bank_account_number'),
            'bank_account_name' => Setting::get('bank_account_name'),
            'payment_instructions' => Setting::get('payment_instructions'),
        ]);
    }

    /**
     * User uploads transfer proof for their booking.
     */
    public function submitProof(Request $request, Booking $booking)
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 403);

        if (! in_array($booking->status, ['pending', 'awaiting_verification'])) {
            throw ValidationException::withMessages([
                'status' => ['Pembayaran untuk pesanan ini tidak dapat diproses.'],
            ]);
        }

        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:255'],
            'sender_name' => ['required', 'string', 'max:255'],
            'proof' => ['required', 'image', 'max:4096'],
        ]);

        $payment = $booking->payment;
        abort_if(! $payment, 404, 'Data pembayaran tidak ditemukan.');

        // Remove previous proof if re-uploading
        if ($payment->proof_path) {
            Storage::disk('public')->delete($payment->proof_path);
        }

        $path = $request->file('proof')->store('payment-proofs', 'public');

        $payment->update([
            'bank_name' => $data['bank_name'],
            'sender_name' => $data['sender_name'],
            'proof_path' => $path,
            'status' => 'submitted',
            'paid_at' => now(),
        ]);

        $booking->update(['status' => 'awaiting_verification']);

        $booking->load(['villa.images', 'payment']);

        return response()->json($booking);
    }
}

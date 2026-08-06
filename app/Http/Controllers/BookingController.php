<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Villa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $bookings = $request->user()->bookings()
            ->with(['villa.images', 'payment'])
            ->latest()
            ->paginate((int) $request->query('per_page', 10));

        return response()->json($bookings);
    }

    public function show(Request $request, Booking $booking)
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 403);

        $booking->load(['villa.images', 'payment']);

        return response()->json($booking);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'villa_id' => ['required', 'exists:villas,id'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests' => ['required', 'integer', 'min:1'],
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_phone' => ['required', 'string', 'max:30'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'promo_code' => ['nullable', 'string', 'max:50'],
        ], [
            'villa_id.required' => 'Villa belum dipilih.',
            'villa_id.exists' => 'Villa tidak ditemukan.',
            'check_in.required' => 'Tanggal check-in wajib diisi.',
            'check_in.date' => 'Tanggal check-in tidak valid.',
            'check_in.after_or_equal' => 'Tanggal check-in tidak boleh di masa lalu.',
            'check_out.required' => 'Tanggal check-out wajib diisi.',
            'check_out.date' => 'Tanggal check-out tidak valid.',
            'check_out.after' => 'Tanggal check-out harus setelah tanggal check-in.',
            'guests.required' => 'Jumlah tamu wajib diisi.',
            'guests.min' => 'Jumlah tamu minimal 1 orang.',
            'guest_name.required' => 'Nama tamu wajib diisi.',
            'guest_phone.required' => 'Nomor HP / WhatsApp wajib diisi.',
            'guest_email.email' => 'Format email tidak valid.',
        ]);

        $villa = Villa::where('is_active', true)->findOrFail($data['villa_id']);

        if ($data['guests'] > $villa->max_guests) {
            throw ValidationException::withMessages([
                'guests' => ["Kapasitas maksimal villa ini {$villa->max_guests} tamu."],
            ]);
        }

        $checkIn = Carbon::parse($data['check_in'])->startOfDay();
        $checkOut = Carbon::parse($data['check_out'])->startOfDay();
        $nights = $checkIn->diffInDays($checkOut);

        $booking = DB::transaction(function () use ($villa, $data, $checkIn, $checkOut, $nights, $request) {
            // Lock overlapping check inside transaction to avoid double-booking
            $overlap = Booking::where('villa_id', $villa->id)
                ->whereIn('status', Booking::ACTIVE_STATUSES)
                ->where('check_in', '<', $checkOut)
                ->where('check_out', '>', $checkIn)
                ->lockForUpdate()
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'check_in' => ['Villa tidak tersedia pada tanggal yang dipilih.'],
                ]);
            }

            $pricePerNight = (float) $villa->price_per_night;
            $subtotal = $pricePerNight * $nights;
            $feePercent = (float) Setting::get('service_fee_percent', 5);
            $serviceFee = round($subtotal * $feePercent / 100, 2);

            // Promo (opsional)
            $promo = null;
            $discount = 0;
            if (! empty($data['promo_code'])) {
                $promo = \App\Models\Promo::whereRaw('LOWER(code) = ?', [strtolower($data['promo_code'])])
                    ->lockForUpdate()
                    ->first();
                if (! $promo) {
                    throw ValidationException::withMessages(['promo_code' => ['Kode promo tidak ditemukan.']]);
                }
                if ($error = $promo->validationError($subtotal)) {
                    throw ValidationException::withMessages(['promo_code' => [$error]]);
                }
                $discount = $promo->discountFor($subtotal);
            }

            // PPN (opsional, dapat diaktifkan admin di Pengaturan)
            $preTax = $subtotal + $serviceFee - $discount;
            $taxEnabled = filter_var(Setting::get('tax_enabled', false), FILTER_VALIDATE_BOOLEAN);
            $taxPercent = (float) Setting::get('tax_percent', 0);
            $tax = ($taxEnabled && $taxPercent > 0) ? round($preTax * $taxPercent / 100, 2) : 0;

            $total = $preTax + $tax;

            $booking = Booking::create([
                'user_id' => $request->user()->id,
                'villa_id' => $villa->id,
                'promo_id' => $promo?->id,
                'promo_code' => $promo?->code,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'nights' => $nights,
                'guests' => $data['guests'],
                'guest_name' => $data['guest_name'],
                'guest_phone' => $data['guest_phone'],
                'guest_email' => $data['guest_email'] ?? $request->user()->email,
                'price_per_night' => $pricePerNight,
                'subtotal' => $subtotal,
                'service_fee' => $serviceFee,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
            ]);

            if ($promo) {
                $promo->increment('used_count');
            }

            Payment::create([
                'booking_id' => $booking->id,
                'amount' => $total,
                'method' => 'manual_transfer',
                'status' => 'awaiting',
            ]);

            return $booking;
        });

        $booking->load(['villa.images', 'payment']);

        return response()->json($booking, 201);
    }

    public function cancel(Request $request, Booking $booking)
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 403);

        if (! in_array($booking->status, ['pending', 'awaiting_verification'])) {
            throw ValidationException::withMessages([
                'status' => ['Pesanan ini tidak dapat dibatalkan.'],
            ]);
        }

        $booking->update(['status' => 'cancelled']);

        return response()->json($booking);
    }
}

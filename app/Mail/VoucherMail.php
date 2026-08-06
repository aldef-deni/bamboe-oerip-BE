<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VoucherMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $settings;
    public string $logoUrl;

    public function __construct(public Booking $booking)
    {
        $this->settings = Setting::asMap();
        $this->logoUrl = asset('logo-mail.png');
    }

    public function build()
    {
        $brand = $this->settings['site_name'] ?? 'Bamboe Oerip';

        $pdf = Pdf::loadView('pdf.voucher', [
            'booking' => $this->booking,
            'settings' => $this->settings,
        ])->setPaper('a4');

        return $this
            ->subject("E-Voucher {$this->booking->booking_code} — {$brand}")
            ->view('emails.voucher', [
                'booking' => $this->booking,
                'settings' => $this->settings,
                'logoUrl' => $this->logoUrl,
            ])
            ->attachData($pdf->output(), "E-Voucher-{$this->booking->booking_code}.pdf", [
                'mime' => 'application/pdf',
            ]);
    }
}

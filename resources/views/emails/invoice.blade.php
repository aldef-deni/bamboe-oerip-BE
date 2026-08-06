@php
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $green = '#1e5c3c';
    $gold = '#C6902A';
    $brand = $settings['site_name'] ?? 'Bamboe Oerip';
@endphp
<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0; background:#eef2f0; font-family:Arial,Helvetica,sans-serif; color:#374151;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f0; padding:24px 0;">
        <tr><td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:92%; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,.08);">
                <!-- Header -->
                <tr><td style="background:{{ $green }}; padding:26px 32px;" align="center">
                    <img src="{{ $logoUrl }}" alt="{{ $brand }}" height="42" style="height:42px;">
                    <div style="color:#cbe6d6; font-size:11px; margin-top:6px; letter-spacing:1px;">HOMESTAY &amp; PROPERTI</div>
                </td></tr>
                <tr><td style="height:4px; background:{{ $gold }};"></td></tr>

                <!-- Title -->
                <tr><td style="padding:28px 32px 8px;">
                    <div style="font-size:22px; font-weight:bold; color:#111827;">Invoice Pesanan</div>
                    <div style="color:#6b7280; font-size:13px; margin-top:4px;">Halo {{ $booking->guest_name }}, terima kasih telah memesan. Berikut rincian pesanan Anda.</div>
                </td></tr>

                <!-- Meta -->
                <tr><td style="padding:12px 32px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6faf7; border:1px solid #e6efe9; border-radius:10px;">
                        <tr>
                            <td style="padding:14px 16px;">
                                <div style="color:#9ca3af; font-size:11px;">No. Invoice</div>
                                <div style="font-weight:bold; color:#111827;">{{ $booking->booking_code }}</div>
                            </td>
                            <td style="padding:14px 16px; text-align:right;">
                                <div style="color:#9ca3af; font-size:11px;">Tanggal</div>
                                <div style="font-weight:bold; color:#111827;">{{ $booking->created_at->timezone('Asia/Jakarta')->format('d/m/Y') }}</div>
                            </td>
                        </tr>
                    </table>
                </td></tr>

                <!-- Booking detail -->
                <tr><td style="padding:8px 32px;">
                    <div style="font-size:16px; font-weight:bold; color:#111827;">{{ $booking->villa->name ?? '-' }}</div>
                    <div style="color:#6b7280; font-size:13px; margin-top:2px;">{{ $booking->villa->city ?? '' }}</div>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;">
                        <tr>
                            <td style="padding:6px 0;"><span style="color:#9ca3af; font-size:12px;">Check-in:</span> <strong>{{ $booking->check_in->format('d/m/Y') }}</strong></td>
                            <td style="padding:6px 0; text-align:right;"><span style="color:#9ca3af; font-size:12px;">Check-out:</span> <strong>{{ $booking->check_out->format('d/m/Y') }}</strong></td>
                        </tr>
                        <tr>
                            <td style="padding:6px 0;"><span style="color:#9ca3af; font-size:12px;">Durasi:</span> <strong>{{ $booking->nights }} malam</strong></td>
                            <td style="padding:6px 0; text-align:right;"><span style="color:#9ca3af; font-size:12px;">Tamu:</span> <strong>{{ $booking->guests }} orang</strong></td>
                        </tr>
                    </table>
                </td></tr>

                <!-- Breakdown -->
                <tr><td style="padding:14px 32px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">
                        <tr>
                            <td style="padding:7px 0; border-bottom:1px solid #eef1f0;">{{ $rp($booking->price_per_night) }} × {{ $booking->nights }} malam</td>
                            <td style="padding:7px 0; border-bottom:1px solid #eef1f0; text-align:right;">{{ $rp($booking->subtotal) }}</td>
                        </tr>
                        @if ((float) $booking->service_fee > 0)
                        <tr><td style="padding:7px 0; border-bottom:1px solid #eef1f0;">Biaya layanan</td><td style="padding:7px 0; border-bottom:1px solid #eef1f0; text-align:right;">{{ $rp($booking->service_fee) }}</td></tr>
                        @endif
                        @if ((float) $booking->discount > 0)
                        <tr><td style="padding:7px 0; border-bottom:1px solid #eef1f0; color:#059669;">Diskon promo{{ $booking->promo_code ? ' ('.$booking->promo_code.')' : '' }}</td><td style="padding:7px 0; border-bottom:1px solid #eef1f0; text-align:right; color:#059669;">- {{ $rp($booking->discount) }}</td></tr>
                        @endif
                        @if ((float) ($booking->tax ?? 0) > 0)
                        <tr><td style="padding:7px 0; border-bottom:1px solid #eef1f0;">PPN</td><td style="padding:7px 0; border-bottom:1px solid #eef1f0; text-align:right;">{{ $rp($booking->tax) }}</td></tr>
                        @endif
                        <tr>
                            <td style="padding:12px 0 0; font-size:16px; font-weight:bold; color:{{ $green }};">Total</td>
                            <td style="padding:12px 0 0; font-size:16px; font-weight:bold; color:{{ $green }}; text-align:right;">{{ $rp($booking->total) }}</td>
                        </tr>
                    </table>
                </td></tr>

                @if ($booking->status !== 'confirmed' && $booking->status !== 'completed')
                <!-- Payment -->
                <tr><td style="padding:8px 32px 4px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $green }}; border-radius:12px;">
                        <tr><td style="padding:18px 20px; color:#fff;">
                            <div style="color:#bfe0cd; font-size:12px;">Silakan transfer ke rekening</div>
                            <div style="font-size:16px; font-weight:bold; margin-top:2px;">{{ $settings['bank_name'] ?? '-' }} — {{ $settings['bank_account_number'] ?? '-' }}</div>
                            <div style="color:#bfe0cd; font-size:12px; margin-top:2px;">a.n. {{ $settings['bank_account_name'] ?? '-' }}</div>
                            <div style="margin-top:12px; border-top:1px solid rgba(255,255,255,.2); padding-top:12px;">
                                <span style="color:#bfe0cd; font-size:12px;">Total tagihan:</span>
                                <span style="font-size:18px; font-weight:bold;"> {{ $rp($booking->total) }}</span>
                            </div>
                        </td></tr>
                    </table>
                    <div style="color:#6b7280; font-size:12px; margin-top:10px;">Setelah transfer, unggah bukti pembayaran melalui akun Anda. Pesanan akan diverifikasi maksimal 1×24 jam.</div>
                </td></tr>
                @endif

                <tr><td style="padding:14px 32px 4px;">
                    <div style="color:#6b7280; font-size:12px;">📎 Invoice versi PDF terlampir pada email ini.</div>
                </td></tr>

                <!-- Footer -->
                <tr><td style="padding:22px 32px; background:#f6faf7; margin-top:12px; color:#9ca3af; font-size:11px; text-align:center;">
                    Terima kasih telah memesan bersama <strong style="color:{{ $green }};">{{ $brand }}</strong>.<br>
                    @if ($settings['contact_phone'] ?? null) Telp {{ $settings['contact_phone'] }} @endif
                    @if ($settings['contact_email'] ?? null) · {{ $settings['contact_email'] }} @endif
                </td></tr>
            </table>
            <div style="color:#9ca3af; font-size:11px; margin-top:14px;">© {{ date('Y') }} {{ $brand }}. Email otomatis, mohon tidak membalas.</div>
        </td></tr>
    </table>
</body>
</html>

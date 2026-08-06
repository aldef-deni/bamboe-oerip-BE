@php
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
                <tr><td style="background:{{ $green }}; padding:26px 32px;" align="center">
                    <img src="{{ $logoUrl }}" alt="{{ $brand }}" height="42" style="height:42px;">
                    <div style="color:#cbe6d6; font-size:11px; margin-top:6px; letter-spacing:1px;">HOMESTAY &amp; PROPERTI</div>
                </td></tr>
                <tr><td style="height:4px; background:{{ $gold }};"></td></tr>

                <tr><td style="padding:28px 32px 6px;" align="center">
                    <div style="display:inline-block; background:#e7f4ec; color:{{ $green }}; font-size:12px; font-weight:bold; padding:5px 16px; border-radius:999px;">PEMBAYARAN TERKONFIRMASI ✓</div>
                    <div style="font-size:22px; font-weight:bold; color:#111827; margin-top:14px;">Pesanan Anda Dikonfirmasi!</div>
                    <div style="color:#6b7280; font-size:13px; margin-top:6px;">Halo {{ $booking->guest_name }}, berikut e-voucher Anda. Tunjukkan saat check-in.</div>
                </td></tr>

                <!-- Voucher code -->
                <tr><td style="padding:18px 32px 6px;" align="center">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:2px dashed {{ $green }}; border-radius:14px;">
                        <tr><td align="center" style="padding:18px;">
                            <div style="color:#9ca3af; font-size:11px; letter-spacing:2px;">KODE PEMESANAN</div>
                            <div style="font-size:30px; font-weight:bold; letter-spacing:5px; color:{{ $green }}; margin-top:4px;">{{ $booking->booking_code }}</div>
                        </td></tr>
                    </table>
                </td></tr>

                <tr><td style="padding:10px 32px;" align="center">
                    <div style="font-size:17px; font-weight:bold; color:#111827;">{{ $booking->villa->name ?? '-' }}</div>
                    <div style="color:#6b7280; font-size:13px;">{{ $booking->villa->city ?? '' }}</div>
                </td></tr>

                <!-- Detail grid -->
                <tr><td style="padding:10px 32px 4px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px; border-top:1px solid #eef1f0;">
                        <tr>
                            <td style="padding:12px 8px; text-align:center; width:25%;"><div style="color:#9ca3af; font-size:11px;">Check-in</div><div style="font-weight:bold; color:#111827;">{{ $booking->check_in->format('d/m/Y') }}</div></td>
                            <td style="padding:12px 8px; text-align:center; width:25%;"><div style="color:#9ca3af; font-size:11px;">Check-out</div><div style="font-weight:bold; color:#111827;">{{ $booking->check_out->format('d/m/Y') }}</div></td>
                            <td style="padding:12px 8px; text-align:center; width:25%;"><div style="color:#9ca3af; font-size:11px;">Malam</div><div style="font-weight:bold; color:#111827;">{{ $booking->nights }}</div></td>
                            <td style="padding:12px 8px; text-align:center; width:25%;"><div style="color:#9ca3af; font-size:11px;">Tamu</div><div style="font-weight:bold; color:#111827;">{{ $booking->guests }}</div></td>
                        </tr>
                    </table>
                </td></tr>

                <tr><td style="padding:6px 32px 4px;">
                    <div style="background:#f6faf7; border:1px dashed {{ $green }}; border-radius:10px; padding:12px 16px; text-align:center; color:{{ $green }}; font-size:12px;">
                        Tunjukkan e-voucher ini (cetak atau di layar) saat check-in di properti.
                    </div>
                </td></tr>

                <tr><td style="padding:12px 32px 4px;">
                    <div style="color:#6b7280; font-size:12px;">📎 E-voucher versi PDF terlampir pada email ini.</div>
                </td></tr>

                <tr><td style="padding:22px 32px; background:#f6faf7; color:#9ca3af; font-size:11px; text-align:center;">
                    Selamat menikmati menginap Anda bersama <strong style="color:{{ $green }};">{{ $brand }}</strong>.<br>
                    @if ($settings['contact_phone'] ?? null) Telp {{ $settings['contact_phone'] }} @endif
                    @if ($settings['contact_email'] ?? null) · {{ $settings['contact_email'] }} @endif
                </td></tr>
            </table>
            <div style="color:#9ca3af; font-size:11px; margin-top:14px;">© {{ date('Y') }} {{ $brand }}. Email otomatis, mohon tidak membalas.</div>
        </td></tr>
    </table>
</body>
</html>

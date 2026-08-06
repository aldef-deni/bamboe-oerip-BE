@php
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $green = '#1e5c3c';
    $green2 = '#257449';
    $gold = '#C6902A';
    $statusLabel = [
        'pending' => 'Menunggu Pembayaran',
        'awaiting_verification' => 'Menunggu Verifikasi',
        'confirmed' => 'Lunas / Dikonfirmasi',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
        'rejected' => 'Ditolak',
    ][$booking->status] ?? $booking->status;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { margin: 0; color: #374151; font-size: 12px; }
    .header { background: {{ $green }}; color: #fff; padding: 24px 32px; }
    .header table { width: 100%; }
    .brand-name { font-size: 20px; font-weight: bold; color: #fff; }
    .brand-sub { font-size: 10px; color: #cbe6d6; }
    .doc-title { font-size: 26px; font-weight: bold; color: #fff; text-align: right; letter-spacing: 2px; }
    .doc-meta { font-size: 10px; color: #cbe6d6; text-align: right; }
    .goldbar { height: 4px; background: {{ $gold }}; }
    .wrap { padding: 24px 32px; }
    .muted { color: #9ca3af; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }
    .sec { margin-bottom: 18px; }
    .box { background: #f6faf7; border: 1px solid #e6efe9; border-radius: 8px; padding: 14px 16px; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.items th { background: {{ $green }}; color: #fff; text-align: left; padding: 9px 12px; font-size: 11px; }
    table.items td { padding: 9px 12px; border-bottom: 1px solid #eef1f0; }
    table.items td.r, table.items th.r { text-align: right; }
    .totals { width: 55%; margin-left: 45%; margin-top: 10px; }
    .totals td { padding: 5px 12px; }
    .totals .grand td { border-top: 2px solid {{ $green }}; font-size: 14px; font-weight: bold; color: {{ $green }}; padding-top: 8px; }
    .badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 10px; font-weight: bold; background: #e7f4ec; color: {{ $green2 }}; }
    .pay { background: {{ $green }}; color: #fff; border-radius: 8px; padding: 14px 16px; }
    .pay .k { color: #bfe0cd; font-size: 10px; }
    .pay .v { color: #fff; font-size: 14px; font-weight: bold; }
    .footer { margin-top: 22px; border-top: 1px solid #eef1f0; padding-top: 12px; color: #9ca3af; font-size: 10px; text-align: center; }
</style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td style="width:55%; vertical-align:middle;">
                    @if (extension_loaded('gd') && file_exists(public_path('logo-mail.png')))
                        <img src="{{ public_path('logo-mail.png') }}" style="height:42px;"><br>
                    @else
                        <span style="font-size:20px; font-weight:bold; color:#fff;">{{ $settings['site_name'] ?? 'Bamboe Oerip' }}</span><br>
                    @endif
                    <span class="brand-sub">Homestay &amp; Properti</span>
                </td>
                <td style="vertical-align:middle;">
                    <div class="doc-title">INVOICE</div>
                    <div class="doc-meta">No. {{ $booking->booking_code }}</div>
                    <div class="doc-meta">Tanggal: {{ $booking->created_at->timezone('Asia/Jakarta')->format('d/m/Y') }}</div>
                </td>
            </tr>
        </table>
    </div>
    <div class="goldbar"></div>

    <div class="wrap">
        <table style="width:100%;" class="sec">
            <tr>
                <td style="width:50%; vertical-align:top;">
                    <div class="muted">Ditagihkan kepada</div>
                    <div style="font-size:14px; font-weight:bold; color:#111827; margin-top:3px;">{{ $booking->guest_name }}</div>
                    <div style="margin-top:2px;">{{ $booking->guest_email }}</div>
                    <div>{{ $booking->guest_phone }}</div>
                </td>
                <td style="width:50%; vertical-align:top;">
                    <div class="muted">Status</div>
                    <div style="margin-top:4px;"><span class="badge">{{ $statusLabel }}</span></div>
                    @if ($settings['site_name'] ?? null)
                        <div class="muted" style="margin-top:10px;">Diterbitkan oleh</div>
                        <div style="margin-top:2px; font-weight:bold; color:#111827;">{{ $settings['site_name'] }}</div>
                    @endif
                </td>
            </tr>
        </table>

        <div class="sec box">
            <table style="width:100%;">
                <tr>
                    <td style="width:50%;">
                        <div class="muted">Penginapan</div>
                        <div style="font-weight:bold; color:#111827; font-size:13px;">{{ $booking->villa->name ?? '-' }}</div>
                        <div style="color:#6b7280;">{{ $booking->villa->city ?? '' }}</div>
                    </td>
                    <td style="width:25%;">
                        <div class="muted">Check-in</div>
                        <div style="font-weight:bold;">{{ $booking->check_in->format('d/m/Y') }}</div>
                    </td>
                    <td style="width:25%;">
                        <div class="muted">Check-out</div>
                        <div style="font-weight:bold;">{{ $booking->check_out->format('d/m/Y') }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding-top:10px;">
                        <div class="muted">Tamu</div>
                        <div style="font-weight:bold;">{{ $booking->guests }} orang</div>
                    </td>
                    <td colspan="2" style="padding-top:10px;">
                        <div class="muted">Durasi</div>
                        <div style="font-weight:bold;">{{ $booking->nights }} malam</div>
                    </td>
                </tr>
            </table>
        </div>

        <table class="items">
            <tr>
                <th>Deskripsi</th>
                <th class="r">Jumlah</th>
            </tr>
            <tr>
                <td>{{ $booking->villa->name ?? 'Penginapan' }} — {{ $rp($booking->price_per_night) }} × {{ $booking->nights }} malam</td>
                <td class="r">{{ $rp($booking->subtotal) }}</td>
            </tr>
            @if ((float) $booking->service_fee > 0)
                <tr><td>Biaya layanan</td><td class="r">{{ $rp($booking->service_fee) }}</td></tr>
            @endif
            @if ((float) $booking->discount > 0)
                <tr><td>Diskon promo{{ $booking->promo_code ? ' ('.$booking->promo_code.')' : '' }}</td><td class="r" style="color:#059669;">- {{ $rp($booking->discount) }}</td></tr>
            @endif
            @if ((float) ($booking->tax ?? 0) > 0)
                <tr><td>PPN</td><td class="r">{{ $rp($booking->tax) }}</td></tr>
            @endif
        </table>

        <table class="totals">
            <tr class="grand"><td>Total</td><td style="text-align:right;">{{ $rp($booking->total) }}</td></tr>
        </table>

        @if ($booking->status !== 'confirmed' && $booking->status !== 'completed')
            <div class="sec pay" style="margin-top:20px;">
                <table style="width:100%;">
                    <tr>
                        <td>
                            <div class="k">Silakan transfer ke rekening</div>
                            <div class="v">{{ $settings['bank_name'] ?? '-' }} — {{ $settings['bank_account_number'] ?? '-' }}</div>
                            <div class="k" style="margin-top:2px;">a.n. {{ $settings['bank_account_name'] ?? '-' }}</div>
                        </td>
                        <td style="text-align:right;">
                            <div class="k">Total tagihan</div>
                            <div class="v" style="font-size:18px;">{{ $rp($booking->total) }}</div>
                        </td>
                    </tr>
                </table>
            </div>
            <div style="color:#6b7280; font-size:10px; margin-top:8px;">Setelah transfer, unggah bukti pembayaran melalui akun Anda. Pesanan diverifikasi maksimal 1×24 jam.</div>
        @endif

        <div class="footer">
            Terima kasih telah memesan bersama {{ $settings['site_name'] ?? 'Bamboe Oerip' }}.<br>
            Invoice ini dibuat otomatis oleh sistem.
        </div>
    </div>
</body>
</html>

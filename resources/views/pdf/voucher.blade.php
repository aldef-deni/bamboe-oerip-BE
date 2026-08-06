@php
    $green = '#1e5c3c';
    $green2 = '#257449';
    $gold = '#C6902A';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { margin: 0; color: #374151; font-size: 12px; }
    .sheet { padding: 30px 34px; }
    .card { border: 2px solid {{ $green }}; border-radius: 16px; overflow: hidden; }
    .top { background: {{ $green }}; color: #fff; padding: 22px 26px; }
    .top table { width: 100%; }
    .brand-sub { font-size: 10px; color: #cbe6d6; }
    .vtag { text-align: right; font-size: 22px; font-weight: bold; letter-spacing: 3px; color: #fff; }
    .vtag small { display:block; font-size: 10px; letter-spacing: 2px; color: {{ '#f3d778' }}; font-weight: normal; }
    .goldbar { height: 4px; background: {{ $gold }}; }
    .codewrap { text-align: center; padding: 22px 20px 10px; }
    .codelabel { color: #9ca3af; font-size: 10px; letter-spacing: 2px; text-transform: uppercase; }
    .code { font-size: 34px; font-weight: bold; letter-spacing: 6px; color: {{ $green }}; margin-top: 4px; }
    .villa { text-align:center; font-size: 18px; font-weight: bold; color:#111827; margin-top: 6px; }
    .villa small { display:block; color:#6b7280; font-size: 11px; font-weight: normal; margin-top: 2px; }
    .grid { width: 100%; border-collapse: collapse; margin-top: 16px; }
    .grid td { width: 25%; padding: 12px 10px; text-align: center; border-top: 1px solid #eef1f0; }
    .grid .lab { color:#9ca3af; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; }
    .grid .val { font-weight: bold; color:#111827; font-size: 13px; margin-top: 3px; }
    .note { margin: 4px 26px 22px; background:#f6faf7; border:1px dashed {{ $green2 }}; border-radius: 10px; padding: 12px 16px; text-align:center; color:{{ $green2 }}; font-size: 11px; }
    .footer { margin-top: 18px; color: #9ca3af; font-size: 10px; text-align: center; }
    .badge { display:inline-block; padding:4px 14px; border-radius:999px; background:#e7f4ec; color:{{ $green2 }}; font-size:10px; font-weight:bold; }
</style>
</head>
<body>
<div class="sheet">
    <div class="card">
        <div class="top">
            <table>
                <tr>
                    <td style="vertical-align:middle;">
                        @if (extension_loaded('gd') && file_exists(public_path('logo-mail.png')))
                            <img src="{{ public_path('logo-mail.png') }}" style="height:42px;"><br>
                        @else
                            <span style="font-size:20px; font-weight:bold; color:#fff;">{{ $settings['site_name'] ?? 'Bamboe Oerip' }}</span><br>
                        @endif
                        <span class="brand-sub">Homestay &amp; Properti</span>
                    </td>
                    <td class="vtag" style="vertical-align:middle;">E-VOUCHER<small>BUKTI PEMESANAN</small></td>
                </tr>
            </table>
        </div>
        <div class="goldbar"></div>

        <div class="codewrap">
            <div class="codelabel">Kode Pemesanan</div>
            <div class="code">{{ $booking->booking_code }}</div>
            <div style="margin-top:8px;"><span class="badge">TERKONFIRMASI</span></div>
            <div class="villa">{{ $booking->villa->name ?? '-' }}<small>{{ $booking->villa->city ?? '' }}</small></div>
        </div>

        <table class="grid">
            <tr>
                <td>
                    <div class="lab">Check-in</div>
                    <div class="val">{{ $booking->check_in->format('d/m/Y') }}</div>
                </td>
                <td>
                    <div class="lab">Check-out</div>
                    <div class="val">{{ $booking->check_out->format('d/m/Y') }}</div>
                </td>
                <td>
                    <div class="lab">Malam</div>
                    <div class="val">{{ $booking->nights }}</div>
                </td>
                <td>
                    <div class="lab">Tamu</div>
                    <div class="val">{{ $booking->guests }} orang</div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="lab">Atas Nama</div>
                    <div class="val">{{ $booking->guest_name }}</div>
                </td>
                <td colspan="2">
                    <div class="lab">Kontak</div>
                    <div class="val" style="font-size:11px;">{{ $booking->guest_phone }}</div>
                </td>
            </tr>
        </table>

        <div class="note">
            Tunjukkan e-voucher ini (cetak atau di layar) saat check-in di properti.
        </div>
    </div>

    <div class="footer">
        {{ $settings['site_name'] ?? 'Bamboe Oerip' }}
        @if ($settings['contact_phone'] ?? null) · Telp {{ $settings['contact_phone'] }} @endif
        @if ($settings['contact_email'] ?? null) · {{ $settings['contact_email'] }} @endif
        <br>Terima kasih & selamat menikmati menginap Anda.
    </div>
</div>
</body>
</html>

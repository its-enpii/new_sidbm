@php
    $legalName = strtoupper($identity['legal_name'] ?? $identity['short_name'] ?? config('app.name'));
    $district = $identity['district_name'] ?? '';
    $regency = $identity['regency_name'] ?? '';
    $locationLine = trim(($district !== '' ? 'Kec. ' . $district : '') . ' ' . ($regency !== '' ? 'Kab. ' . $regency : ''));
    $registration = $identity['registration_number'] ?? '';
    $address = $identity['address'] ?? '';
    $phone = $identity['phone'] ?? '';
    $logoUrl = $identity['logo_url'] ?? null;

    $infoLine = $address;
    if ($address !== '' && $phone !== '') {
        $infoLine .= ', Telp. ' . $phone;
    } elseif ($phone !== '') {
        $infoLine = 'Telp. ' . $phone;
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Kartu Angsuran #{{ $loan['id'] }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 75px; margin-left: 94px; }
        body { font-size: 11px; color: #111; }
        table { border-collapse: collapse; }
        table tr th, table tr td { padding: 2px 4px; }
        .l { border-left: 1px solid #000; }
        .t { border-top: 1px solid #000; }
        .r { border-right: 1px solid #000; }
        .b { border-bottom: 1px solid #000; }
    </style>
</head>
<body>
    <table width="100%" style="border-bottom: 1px solid grey;">
        <tr>
            @if (! empty($logoUrl))
                <td width="30">
                    <img src="{{ $logoUrl }}" width="40" alt="Logo">
                </td>
            @endif
            <td>
                <div style="font-size: 12px;">{{ $legalName }}</div>
                <div style="font-size: 12px;">
                    <b>{{ strtoupper($locationLine) }}</b>
                </div>
            </td>
        </tr>
    </table>
    <table width="100%" style="position: relative; top: -10px;">
        <tr>
            <td>
                <span style="font-size: 8px; color: grey;">
                    <i>{{ $registration !== '' ? 'SK Kemenkumham RI No. ' . $registration : '' }}</i>
                </span>
            </td>
            <td align="right">
                <span style="font-size: 8px; color: grey;">
                    <i>{{ $infoLine }}</i>
                </span>
            </td>
        </tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr>
            <td align="center">
                <div style="font-size: 18px;"><b>KARTU ANGSURAN</b></div>
                <div style="font-size: 12px;">
                    Pinjaman #{{ $loan['id'] }}@if(!empty($loan['loan_number'])) &middot; {{ $loan['loan_number'] }}@endif
                </div>
            </td>
        </tr>
        <tr><td height="10"></td></tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; border: 1px solid #999; margin-bottom: 10px;">
        <tr>
            <td width="20%" style="padding: 4px 6px; color: #555;">Kelompok</td>
            <td width="30%" style="padding: 4px 6px;">{{ $loan['group_name'] ?? '—' }}@if(!empty($loan['village_name'])) &middot; {{ $loan['village_name'] }}@endif</td>
            <td width="20%" style="padding: 4px 6px; color: #555;">Produk</td>
            <td width="30%" style="padding: 4px 6px;">{{ strtoupper($loan['product_code'] ?? '—') }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 6px; color: #555;">Alokasi</td>
            <td style="padding: 4px 6px;" align="right">{{ number_format($loan['principal_amount'], 2) }}</td>
            <td style="padding: 4px 6px; color: #555;">Jasa / jangka</td>
            <td style="padding: 4px 6px;">{{ number_format($loan['interest_rate'], 2) }}% &middot; {{ $loan['term_months'] }} bln</td>
        </tr>
        <tr>
            <td style="padding: 4px 6px; color: #555;">Tgl cair</td>
            <td style="padding: 4px 6px;">{{ $loan['disbursed_at'] ? \Carbon\CarbonImmutable::parse($loan['disbursed_at'])->format('d/m/Y') : '—' }}</td>
            <td style="padding: 4px 6px; color: #555;">Pemanfaat</td>
            <td style="padding: 4px 6px;">{{ $loan['beneficiaries_count'] ?? 0 }} orang</td>
        </tr>
        @if(!empty($committee))
            <tr>
                <td style="padding: 4px 6px; color: #555;">Pengurus</td>
                <td colspan="3" style="padding: 4px 6px;">
                    @foreach($committee as $c)
                        {{ $c['position'] }}: {{ $c['name'] }}@if(!$loop->last); @endif
                    @endforeach
                </td>
            </tr>
        @endif
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <tr style="background: rgb(74, 74, 74); font-weight: bold; color: #fff;">
            <td align="center" height="20">Ke</td>
            <td>Jatuh tempo</td>
            <td align="right">Pokok</td>
            <td align="right">Jasa</td>
            <td align="right">Bayar Pokok</td>
            <td align="right">Bayar Jasa</td>
            <td align="right">Sisa Pokok</td>
            <td align="right">Sisa Jasa</td>
            <td align="center">Status</td>
        </tr>
        @forelse($rows as $row)
            <tr style="background: {{ $loop->iteration % 2 == 1 ? 'rgb(230, 230, 230)' : 'rgba(255, 255, 255)' }};">
                <td align="center">{{ $row['installment_number'] }}</td>
                <td>{{ $row['due_date'] ? \Carbon\CarbonImmutable::parse($row['due_date'])->format('d/m/Y') : '—' }}</td>
                <td align="right">{{ number_format($row['principal_due'], 2) }}</td>
                <td align="right">{{ number_format($row['interest_due'], 2) }}</td>
                <td align="right">{{ number_format($row['principal_paid'], 2) }}</td>
                <td align="right">{{ number_format($row['interest_paid'], 2) }}</td>
                <td align="right">{{ number_format($row['principal_remaining'], 2) }}</td>
                <td align="right">{{ number_format($row['interest_remaining'], 2) }}</td>
                <td align="center">
                    @if($row['status'] === 'paid')
                        <span style="color: #060;">Lunas</span>
                    @elseif($row['status'] === 'partial')
                        <span style="color: #960;">Sebagian</span>
                    @else
                        Belum
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" align="center" style="color: #666; font-size: 10px;">Belum ada jadwal.</td>
            </tr>
        @endforelse
        @if(count($rows) > 0)
            <tr style="background: rgb(167, 167, 167); font-weight: bold;">
                <td colspan="2">Jumlah</td>
                <td align="right">{{ number_format($totals['plan_principal'], 2) }}</td>
                <td align="right">{{ number_format($totals['plan_interest'], 2) }}</td>
                <td align="right">{{ number_format($totals['paid_principal'], 2) }}</td>
                <td align="right">{{ number_format($totals['paid_interest'], 2) }}</td>
                <td align="right">{{ number_format($totals['remaining_principal'], 2) }}</td>
                <td align="right">{{ number_format($totals['remaining_interest'], 2) }}</td>
                <td></td>
            </tr>
        @endif
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 9px; color: #666; margin-top: 12px;">
        <tr>
            <td align="center">
                Dicetak {{ now()->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
            </td>
        </tr>
    </table>
</body>
</html>
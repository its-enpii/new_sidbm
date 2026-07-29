<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bukti Angsuran #{{ $entry['id'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 24px; }
        h1 { font-size: 16px; margin: 0 0 4px; text-align: center; }
        h2 { font-size: 12px; margin: 0 0 12px; text-align: center; font-weight: normal; color: #444; }
        .meta { text-align: center; margin-bottom: 16px; color: #333; }
        .box { border: 1px solid #333; padding: 10px 12px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 6px; vertical-align: top; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; color: #555; border-bottom: 1px solid #999; }
        td.num { text-align: right; white-space: nowrap; }
        .label { width: 32%; color: #555; }
        .total { font-weight: bold; font-size: 13px; border-top: 1px solid #333; }
        .muted { color: #666; font-size: 10px; }
        .sign { margin-top: 28px; width: 100%; }
        .sign td { width: 50%; text-align: center; padding-top: 40px; }
    </style>
</head>
<body>
    <div class="meta">
        <div style="font-weight:bold;font-size:13px">{{ $identity['legal_name'] ?? $identity['short_name'] ?? config('app.name') }}</div>
        @if(!empty($identity['address']))
            <div class="muted">{{ $identity['address'] }}</div>
        @endif
    </div>

    <h1>Bukti Penerimaan Angsuran</h1>
    <h2>
        No. {{ $entry['journal_number'] ?: $entry['id'] }}
        · {{ \Carbon\CarbonImmutable::parse($entry['transaction_date'])->format('d/m/Y') }}
    </h2>

    <div class="box">
        <table>
            <tr>
                <td class="label">Pinjaman</td>
                <td>
                    @if($loan)
                        #{{ $loan['id'] }}
                        @if($loan['loan_number']) · {{ $loan['loan_number'] }}@endif
                        @if($loan['product_code']) · {{ strtoupper($loan['product_code']) }}@endif
                    @else
                        —
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Kelompok</td>
                <td>{{ $loan['group_name'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Penyetor</td>
                <td>{{ $payer['name'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Keterangan</td>
                <td>{{ $entry['description'] ?? '—' }}</td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>Rincian</th>
                <th class="num">Nominal</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Pokok</td>
                <td class="num">{{ number_format($amounts['principal'], 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Jasa</td>
                <td class="num">{{ number_format($amounts['interest'], 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Denda</td>
                <td class="num">{{ number_format($amounts['penalty'], 0, ',', '.') }}</td>
            </tr>
            <tr class="total">
                <td>Total diterima</td>
                <td class="num">Rp {{ number_format($amounts['total'], 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <p class="muted" style="margin-top:14px">Jurnal:</p>
    <table>
        <thead>
            <tr>
                <th>Akun</th>
                <th class="num">Debit</th>
                <th class="num">Kredit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lines as $line)
                <tr>
                    <td>{{ $line['account_code'] }} · {{ $line['account_name'] }}</td>
                    <td class="num">{{ $line['debit'] > 0 ? number_format($line['debit'], 0, ',', '.') : '' }}</td>
                    <td class="num">{{ $line['credit'] > 0 ? number_format($line['credit'], 0, ',', '.') : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="sign">
        <tr>
            <td>Penyetor<br><br><br><br>( {{ $payer['name'] ?? '………………' }} )</td>
            <td>Petugas<br><br><br><br>( ……………… )</td>
        </tr>
    </table>

    <p class="muted" style="margin-top:16px;text-align:center">
        Dicetak {{ now()->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
        · ID jurnal {{ $entry['id'] }}
    </p>
</body>
</html>

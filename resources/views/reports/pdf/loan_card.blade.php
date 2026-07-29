<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu Angsuran #{{ $loan['id'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; margin: 18px; }
        h1 { font-size: 14px; margin: 0 0 2px; text-align: center; }
        h2 { font-size: 11px; margin: 0 0 10px; text-align: center; font-weight: normal; color: #444; }
        .meta { text-align: center; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 3px 4px; vertical-align: top; border-bottom: 1px solid #eee; }
        th { background: #e8eef4; border-bottom: 1px solid #999; font-size: 9px; text-transform: uppercase; }
        .num { text-align: right; white-space: nowrap; }
        .ctr { text-align: center; }
        .bold { font-weight: bold; }
        .box { border: 1px solid #333; padding: 8px; margin-bottom: 10px; }
        .label { width: 28%; color: #555; }
        .paid { color: #060; }
        .partial { color: #960; }
        .total { background: #f5f5f5; font-weight: bold; border-top: 1px solid #333; }
        .muted { color: #666; font-size: 9px; }
    </style>
</head>
<body>
    <div class="meta">
        <div class="bold" style="font-size:12px">{{ $identity['legal_name'] ?? config('app.name') }}</div>
        @if(!empty($identity['address']))
            <div class="muted">{{ $identity['address'] }}</div>
        @endif
    </div>
    <h1>Kartu Angsuran</h1>
    <h2>Pinjaman #{{ $loan['id'] }}@if(!empty($loan['loan_number'])) · {{ $loan['loan_number'] }}@endif</h2>

    <div class="box">
        <table>
            <tr>
                <td class="label">Kelompok</td>
                <td>{{ $loan['group_name'] ?? '—' }}@if(!empty($loan['village_name'])) · {{ $loan['village_name'] }}@endif</td>
                <td class="label">Produk</td>
                <td>{{ strtoupper($loan['product_code'] ?? '—') }}</td>
            </tr>
            <tr>
                <td class="label">Alokasi</td>
                <td class="num">{{ number_format($loan['principal_amount'], 0, ',', '.') }}</td>
                <td class="label">Jasa / jangka</td>
                <td>{{ number_format($loan['interest_rate'], 2, ',', '.') }}% · {{ $loan['term_months'] }} bln</td>
            </tr>
            <tr>
                <td class="label">Tgl cair</td>
                <td>{{ $loan['disbursed_at'] ? \Carbon\CarbonImmutable::parse($loan['disbursed_at'])->format('d/m/Y') : '—' }}</td>
                <td class="label">Pemanfaat</td>
                <td>{{ $loan['beneficiaries_count'] ?? 0 }} orang</td>
            </tr>
            @if(!empty($committee))
                <tr>
                    <td class="label">Pengurus</td>
                    <td colspan="3">
                        @foreach($committee as $c)
                            {{ $c['position'] }}: {{ $c['name'] }}@if(!$loop->last); @endif
                        @endforeach
                    </td>
                </tr>
            @endif
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th class="ctr">Ke</th>
                <th>Jatuh tempo</th>
                <th class="num">Pokok</th>
                <th class="num">Jasa</th>
                <th class="num">Bayar Pokok</th>
                <th class="num">Bayar Jasa</th>
                <th class="num">Sisa Pokok</th>
                <th class="num">Sisa Jasa</th>
                <th class="ctr">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="ctr">{{ $row['installment_number'] }}</td>
                    <td>{{ $row['due_date'] ? \Carbon\CarbonImmutable::parse($row['due_date'])->format('d/m/Y') : '—' }}</td>
                    <td class="num">{{ number_format($row['principal_due'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['interest_due'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['principal_paid'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['interest_paid'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['principal_remaining'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['interest_remaining'], 0, ',', '.') }}</td>
                    <td class="ctr {{ $row['status'] === 'paid' ? 'paid' : ($row['status'] === 'partial' ? 'partial' : '') }}">
                        {{ $row['status'] === 'paid' ? 'Lunas' : ($row['status'] === 'partial' ? 'Sebagian' : 'Belum') }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="ctr muted">Belum ada jadwal.</td></tr>
            @endforelse
            @if(count($rows) > 0)
                <tr class="total">
                    <td colspan="2">Jumlah</td>
                    <td class="num">{{ number_format($totals['plan_principal'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($totals['plan_interest'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($totals['paid_principal'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($totals['paid_interest'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($totals['remaining_principal'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($totals['remaining_interest'], 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>

    <p class="muted" style="margin-top:12px;text-align:center">
        Dicetak {{ now()->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
    </p>
</body>
</html>

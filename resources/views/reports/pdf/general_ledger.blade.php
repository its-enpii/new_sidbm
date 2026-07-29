@extends('reports.pdf.layout', [
    'title' => 'Buku Besar '.($account['name'] ?? ''),
    'identity' => $identity,
    'period' => $period,
])

@section('content')
<p class="muted">Kode Akun: {{ $account['code'] ?? '' }}</p>
<table>
    <thead>
        <tr>
            <th class="ctr" width="4%">No</th>
            <th class="ctr" width="10%">Tanggal</th>
            <th width="10%">No. Jurnal</th>
            <th>Keterangan</th>
            <th class="num" width="12%">Debit</th>
            <th class="num" width="12%">Kredit</th>
            <th class="num" width="12%">Saldo</th>
        </tr>
    </thead>
    <tbody>
        @if($opening)
            <tr class="sub">
                <td></td>
                <td class="ctr">{{ $opening['year']['date'] }}</td>
                <td></td>
                <td>{{ $opening['year']['label'] }}</td>
                <td class="num">{{ number_format($opening['year']['debit'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format($opening['year']['credit'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format($opening['year']['balance'], 2, ',', '.') }}</td>
            </tr>
            <tr class="sub">
                <td></td>
                <td class="ctr">{{ $opening['prior']['date'] }}</td>
                <td></td>
                <td>{{ $opening['prior']['label'] }}</td>
                <td class="num">{{ number_format($opening['prior']['debit'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format($opening['prior']['credit'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format($opening['prior']['balance'], 2, ',', '.') }}</td>
            </tr>
        @endif
        @foreach($rows as $row)
            <tr>
                <td class="ctr">{{ $row['no'] }}</td>
                <td class="ctr">{{ $row['date'] }}</td>
                <td>{{ $row['journal_number'] }}</td>
                <td>{{ $row['description'] }}</td>
                <td class="num">{{ $row['debit'] ? number_format($row['debit'], 2, ',', '.') : '' }}</td>
                <td class="num">{{ $row['credit'] ? number_format($row['credit'], 2, ',', '.') : '' }}</td>
                <td class="num">{{ number_format($row['balance'], 2, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td colspan="4">{{ $totals['period']['label'] ?? 'Total Transaksi Periode' }}</td>
            <td class="num">{{ number_format($totals['period']['debit'] ?? $totals['debit'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($totals['period']['credit'] ?? $totals['credit'], 2, ',', '.') }}</td>
            <td class="num"></td>
        </tr>
        <tr class="total">
            <td colspan="4">{{ $totals['ytd']['label'] ?? 'Total Transaksi s/d Periode' }}</td>
            <td class="num">{{ number_format($totals['ytd']['debit'] ?? 0, 2, ',', '.') }}</td>
            <td class="num">{{ number_format($totals['ytd']['credit'] ?? 0, 2, ',', '.') }}</td>
            <td class="num">{{ number_format($totals['ytd']['balance'] ?? $totals['closing_balance'], 2, ',', '.') }}</td>
        </tr>
        <tr class="total">
            <td colspan="4">{{ $totals['cumulative']['label'] ?? 'Total Transaksi Kumulatif Tahun' }}</td>
            <td class="num">{{ number_format($totals['cumulative']['debit'] ?? 0, 2, ',', '.') }}</td>
            <td class="num">{{ number_format($totals['cumulative']['credit'] ?? 0, 2, ',', '.') }}</td>
            <td class="num"></td>
        </tr>
    </tbody>
</table>
@endsection

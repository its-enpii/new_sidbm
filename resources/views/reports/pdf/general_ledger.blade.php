@extends('reports.pdf.layout', [
    'title' => 'Buku Besar '.($account['name'] ?? ''),
    'identity' => $identity,
    'period' => $period,
])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="7" align="center">
            <div style="font-size: 18px;"><b>BUKU BESAR</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="7" height="5"></td></tr>
</table>
<p style="font-size: 11px; color: grey; margin: 0 0 4px 0;">Kode Akun: {{ $account['code'] ?? '' }}</p>
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr style="background: rgb(74, 74, 74); font-weight: bold; color: #fff;" height="15">
        <td width="4%" align="center">No</td>
        <td width="10%" align="center">Tanggal</td>
        <td width="10%">No. Jurnal</td>
        <td width="30%">Keterangan</td>
        <td width="14%" align="right">Debit</td>
        <td width="14%" align="right">Kredit</td>
        <td width="14%" align="right">Saldo</td>
    </tr>
    @if($opening)
        <tr style="background: rgb(230, 230, 230); font-weight: bold;">
            <td></td>
            <td align="center">{{ $opening['year']['date'] }}</td>
            <td></td>
            <td>{{ $opening['year']['label'] }}</td>
            <td align="right">{{ number_format($opening['year']['debit'], 2) }}</td>
            <td align="right">{{ number_format($opening['year']['credit'], 2) }}</td>
            <td align="right">{{ number_format($opening['year']['balance'], 2) }}</td>
        </tr>
        <tr style="background: rgb(230, 230, 230); font-weight: bold;">
            <td></td>
            <td align="center">{{ $opening['prior']['date'] }}</td>
            <td></td>
            <td>{{ $opening['prior']['label'] }}</td>
            <td align="right">{{ number_format($opening['prior']['debit'], 2) }}</td>
            <td align="right">{{ number_format($opening['prior']['credit'], 2) }}</td>
            <td align="right">{{ number_format($opening['prior']['balance'], 2) }}</td>
        </tr>
    @endif
    @foreach($rows as $row)
        <tr style="background: {{ $loop->iteration % 2 == 0 ? 'rgb(230, 230, 230)' : 'rgba(255, 255, 255)' }};">
            <td align="center">{{ $row['no'] }}</td>
            <td align="center">{{ $row['date'] }}</td>
            <td>{{ $row['journal_number'] }}</td>
            <td>{{ $row['description'] }}</td>
            <td align="right">{{ $row['debit'] ? number_format($row['debit'], 2) : '' }}</td>
            <td align="right">{{ $row['credit'] ? number_format($row['credit'], 2) : '' }}</td>
            <td align="right">{{ number_format($row['balance'], 2) }}</td>
        </tr>
    @endforeach
    <tr style="background: rgb(167, 167, 167); font-weight: bold;">
        <td colspan="4">{{ $totals['period']['label'] ?? 'Total Transaksi Periode' }}</td>
        <td align="right">{{ number_format($totals['period']['debit'] ?? $totals['debit'], 2) }}</td>
        <td align="right">{{ number_format($totals['period']['credit'] ?? $totals['credit'], 2) }}</td>
        <td align="right"></td>
    </tr>
    <tr style="background: rgb(167, 167, 167); font-weight: bold;">
        <td colspan="4">{{ $totals['ytd']['label'] ?? 'Total Transaksi s/d Periode' }}</td>
        <td align="right">{{ number_format($totals['ytd']['debit'] ?? 0, 2) }}</td>
        <td align="right">{{ number_format($totals['ytd']['credit'] ?? 0, 2) }}</td>
        <td align="right">{{ number_format($totals['ytd']['balance'] ?? $totals['closing_balance'], 2) }}</td>
    </tr>
    <tr style="background: rgb(167, 167, 167); font-weight: bold;">
        <td colspan="4">{{ $totals['cumulative']['label'] ?? 'Total Transaksi Kumulatif Tahun' }}</td>
        <td align="right">{{ number_format($totals['cumulative']['debit'] ?? 0, 2) }}</td>
        <td align="right">{{ number_format($totals['cumulative']['credit'] ?? 0, 2) }}</td>
        <td align="right"></td>
    </tr>
</table>
@endsection

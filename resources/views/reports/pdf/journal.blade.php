@extends('reports.pdf.layout', ['title' => 'Jurnal Transaksi', 'identity' => $identity, 'period' => $period])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="7" align="center">
            <div style="font-size: 18px;"><b>JURNAL TRANSAKSI</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="7" height="5"></td></tr>
    <tr style="background: rgb(74, 74, 74); font-weight: bold; color: #fff;" height="15">
        <td width="4%" align="center">No</td>
        <td width="10%" align="center">Tanggal</td>
        <td width="10%">No. Jurnal</td>
        <td width="8%" align="center">Kode</td>
        <td width="35%">Keterangan</td>
        <td width="15%" align="right">Debit</td>
        <td width="15%" align="right">Kredit</td>
    </tr>
    @foreach($rows as $row)
        <tr style="background: {{ $loop->iteration % 2 == 0 ? 'rgb(230, 230, 230)' : 'rgba(255, 255, 255)' }};">
            <td align="center">{{ $row['no'] }}</td>
            <td align="center">{{ $row['date'] }}</td>
            <td>{{ $row['journal_number'] }}</td>
            <td align="center">{{ $row['account_code'] }}</td>
            <td>{{ $row['account_name'] }}{{ $row['description'] ? ' — '.$row['description'] : '' }}</td>
            <td align="right">{{ $row['debit'] ? number_format($row['debit'], 2) : '' }}</td>
            <td align="right">{{ $row['credit'] ? number_format($row['credit'], 2) : '' }}</td>
        </tr>
    @endforeach
    <tr style="background: rgb(167, 167, 167); font-weight: bold;">
        <td colspan="5">Total</td>
        <td align="right">{{ number_format($totals['debit'], 2) }}</td>
        <td align="right">{{ number_format($totals['credit'], 2) }}</td>
    </tr>
</table>
@endsection

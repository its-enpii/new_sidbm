@extends('reports.pdf.layout', ['title' => 'Jurnal Transaksi', 'identity' => $identity, 'period' => $period])

@section('content')
@if(!empty($truncated))
    <p class="muted">Ditampilkan maksimal 5.000 baris.</p>
@endif
<table>
    <thead>
        <tr>
            <th class="ctr" width="4%">No</th>
            <th class="ctr" width="10%">Tanggal</th>
            <th width="10%">No. Jurnal</th>
            <th width="10%">Kode</th>
            <th>Keterangan</th>
            <th class="num" width="12%">Debit</th>
            <th class="num" width="12%">Kredit</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
            <tr>
                <td class="ctr">{{ $row['no'] }}</td>
                <td class="ctr">{{ $row['date'] }}</td>
                <td>{{ $row['journal_number'] }}</td>
                <td>{{ $row['account_code'] }}</td>
                <td>{{ $row['account_name'] }}{{ $row['description'] ? ' — '.$row['description'] : '' }}</td>
                <td class="num">{{ $row['debit'] ? number_format($row['debit'], 2, ',', '.') : '' }}</td>
                <td class="num">{{ $row['credit'] ? number_format($row['credit'], 2, ',', '.') : '' }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td colspan="5" class="bold">Total</td>
            <td class="num">{{ number_format($totals['debit'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($totals['credit'], 2, ',', '.') }}</td>
        </tr>
    </tbody>
</table>
@endsection

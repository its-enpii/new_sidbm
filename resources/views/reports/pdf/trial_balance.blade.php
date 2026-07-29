@extends('reports.pdf.layout', ['title' => 'Neraca Saldo', 'identity' => $identity, 'period' => $period])

@section('content')
<table>
    <thead>
        <tr>
            <th rowspan="2">Rekening</th>
            <th colspan="2" class="ctr">Neraca Saldo</th>
            <th colspan="2" class="ctr">Laba Rugi</th>
            <th colspan="2" class="ctr">Neraca</th>
        </tr>
        <tr>
            <th class="num">Debit</th>
            <th class="num">Kredit</th>
            <th class="num">Debit</th>
            <th class="num">Kredit</th>
            <th class="num">Debit</th>
            <th class="num">Kredit</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
            <tr>
                <td>{{ $row['code'] }}. {{ $row['name'] }}</td>
                <td class="num">{{ number_format($row['ns_debit'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format($row['ns_credit'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format($row['lr_debit'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format($row['lr_credit'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format($row['bs_debit'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format($row['bs_credit'], 2, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td>Jumlah</td>
            <td class="num">{{ number_format($totals['ns_debit'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($totals['ns_credit'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($totals['lr_debit'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($totals['lr_credit'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($totals['bs_debit'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($totals['bs_credit'], 2, ',', '.') }}</td>
        </tr>
    </tbody>
</table>
<p class="muted">Laba/Rugi berjalan: {{ number_format($net_income, 2, ',', '.') }} · Seimbang: {{ $balanced ? 'Ya' : 'Tidak' }}</p>
@endsection

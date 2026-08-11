@extends('reports.pdf.layout', ['title' => 'Neraca Saldo', 'identity' => $identity, 'period' => $period])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="7" align="center">
            <div style="font-size: 18px;"><b>NERACA</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="7" height="5"></td></tr>
    <tr style="background: rgb(230, 230, 230); font-weight: bold;">
        <td width="40%" rowspan="2" class="t l b" align="center">Rekening</td>
        <td colspan="2" class="t l b" align="center">Neraca Saldo</td>
        <td colspan="2" class="t l b" align="center">Laba Rugi</td>
        <td colspan="2" class="t l b r" align="center">Neraca</td>
    </tr>
    <tr style="background: rgb(230, 230, 230); font-weight: bold;">
        <td class="t l b" align="center">Debit</td>
        <td class="t l b" align="center">Kredit</td>
        <td class="t l b" align="center">Debit</td>
        <td class="t l b" align="center">Kredit</td>
        <td class="t l b" align="center">Debit</td>
        <td class="t l b r" align="center">Kredit</td>
    </tr>
    @foreach($rows as $idx => $row)
        <tr style="background: {{ $idx % 2 === 0 ? 'rgb(230, 230, 230)' : 'rgba(255, 255, 255)' }};">
            <td class="t l b">{{ $row['code'] }}. {{ $row['name'] }}</td>
            <td class="t l b" align="right">
                @if($row['ns_debit'] < 0)({{ number_format(abs($row['ns_debit']), 2) }})@else{{ number_format($row['ns_debit'], 2) }}@endif
            </td>
            <td class="t l b" align="right">
                @if($row['ns_credit'] < 0)({{ number_format(abs($row['ns_credit']), 2) }})@else{{ number_format($row['ns_credit'], 2) }}@endif
            </td>
            <td class="t l b" align="right">
                @if($row['lr_debit'] < 0)({{ number_format(abs($row['lr_debit']), 2) }})@else{{ number_format($row['lr_debit'], 2) }}@endif
            </td>
            <td class="t l b" align="right">
                @if($row['lr_credit'] < 0)({{ number_format(abs($row['lr_credit']), 2) }})@else{{ number_format($row['lr_credit'], 2) }}@endif
            </td>
            <td class="t l b" align="right">
                @if($row['bs_debit'] < 0)({{ number_format(abs($row['bs_debit']), 2) }})@else{{ number_format($row['bs_debit'], 2) }}@endif
            </td>
            <td class="t l b r" align="right">
                @if($row['bs_credit'] < 0)({{ number_format(abs($row['bs_credit']), 2) }})@else{{ number_format($row['bs_credit'], 2) }}@endif
            </td>
        </tr>
    @endforeach
    <tr style="background: rgb(242, 242, 242); font-weight: bold;">
        <td class="t l b">Jumlah</td>
        <td class="t l b" align="right">{{ number_format($totals['ns_debit'], 2) }}</td>
        <td class="t l b" align="right">{{ number_format($totals['ns_credit'], 2) }}</td>
        <td class="t l b" align="right">{{ number_format($totals['lr_debit'], 2) }}</td>
        <td class="t l b" align="right">{{ number_format($totals['lr_credit'], 2) }}</td>
        <td class="t l b" align="right">{{ number_format($totals['bs_debit'], 2) }}</td>
        <td class="t l b r" align="right">{{ number_format($totals['bs_credit'], 2) }}</td>
    </tr>
</table>
@endsection

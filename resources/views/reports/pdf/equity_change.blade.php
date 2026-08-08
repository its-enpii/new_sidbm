@extends('reports.pdf.layout', ['title' => 'Laporan Perubahan Ekuitas', 'identity' => $identity, 'period' => $period])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="3" align="center">
            <div style="font-size: 18px;"><b>LAPORAN PERUBAHAN EKUITAS</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="3" height="5"></td></tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-bottom: 12px;">
    <tr style="background: rgb(232, 232, 232); font-weight: bold;">
        <td width="5%" class="l t" align="center">No</td>
        <td width="55%" class="l t">Ringkasan</td>
        <td width="20%" class="l r t" align="right">Jumlah</td>
    </tr>
    @foreach($bridge as $idx => $item)
        @if($item['key'] === 'closing')
            <tr style="background: rgb(200, 200, 200); font-weight: bold;">
                <td class="l t b" align="center">{{ $idx + 1 }}</td>
                <td class="l t b">{{ $item['label'] }}</td>
                <td class="l t b r" align="right">
                    @if(($item['amount'] ?? 0) < 0)({{ number_format(abs($item['amount']), 2) }})@else{{ number_format($item['amount'], 2) }}@endif
                </td>
            </tr>
        @else
            <tr style="background: {{ $idx % 2 === 0 ? 'rgb(230, 230, 230)' : 'rgba(255, 255, 255)' }};">
                <td class="l t" align="center">{{ $idx + 1 }}</td>
                <td class="l t">{{ $item['label'] }}</td>
                <td class="l t r" align="right">
                    @if(($item['amount'] ?? 0) < 0)({{ number_format(abs($item['amount']), 2) }})@else{{ number_format($item['amount'], 2) }}@endif
                </td>
            </tr>
        @endif
    @endforeach
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr style="background: rgb(232, 232, 232); font-weight: bold;">
        <td class="l t" width="5%" align="center">No</td>
        <td class="l t" width="55%">Rekening Modal</td>
        <td class="l t" width="13%" align="right">Awal</td>
        <td class="l t" width="13%" align="right">Mutasi</td>
        <td class="l r t" width="14%" align="right">Akhir</td>
    </tr>
    @forelse($rows as $idx => $row)
        <tr style="background: {{ $idx % 2 === 0 ? 'rgb(230, 230, 230)' : 'rgba(255, 255, 255)' }};">
            <td class="l t" align="center">{{ $idx + 1 }}</td>
            <td class="l t">{{ $row['code'] }}. {{ $row['name'] }}@if(!empty($row['is_earnings'])) (laba)@endif</td>
            <td class="l t" align="right">
                @if($row['opening'] < 0)({{ number_format(abs($row['opening']), 2) }})@else{{ number_format($row['opening'], 2) }}@endif
            </td>
            <td class="l t" align="right">
                @if(($row['movement'] ?? 0) < 0)({{ number_format(abs($row['movement']), 2) }})@else{{ number_format($row['movement'], 2) }}@endif
            </td>
            <td class="l t r" align="right">
                @if($row['closing'] < 0)({{ number_format(abs($row['closing']), 2) }})@else{{ number_format($row['closing'], 2) }}@endif
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="5" class="l t r" align="center" style="color: grey; font-style: italic;">Tidak ada data.</td>
        </tr>
    @endforelse
    @if(count($rows) > 0)
        <tr style="background: rgb(200, 200, 200); font-weight: bold;">
            <td class="l t b" align="center">&nbsp;</td>
            <td class="l t b">Total ekuitas</td>
            <td class="l t b" align="right">{{ number_format($summary['opening_total'], 2) }}</td>
            <td class="l t b" align="right">{{ number_format($summary['movement_total'], 2) }}</td>
            <td class="l t b r" align="right">{{ number_format($summary['closing_total'], 2) }}</td>
        </tr>
    @endif
</table>
@endsection

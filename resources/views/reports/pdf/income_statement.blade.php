@extends('reports.pdf.layout', ['title' => 'Laporan Laba Rugi', 'identity' => $identity, 'period' => $period])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="4" align="center">
            <div style="font-size: 18px;"><b>LAPORAN LABA RUGI</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="4" height="5"></td></tr>
    <tr style="background: rgb(232, 232, 232); font-weight: bold; font-size: 12px;">
        <td width="55%">Rekening</td>
        <td width="15%" align="right">s.d. {{ $header_lalu }}</td>
        <td width="15%" align="right">{{ $header_sekarang }}</td>
        <td width="15%" align="right">s.d. {{ $header_sekarang }}</td>
    </tr>
    @foreach($groups as $group)
        <tr style="background: rgb(200, 200, 200); font-weight: bold; text-transform: uppercase;">
            <td colspan="4" height="14">{{ $group['code'] }}. {{ $group['name'] }}</td>
        </tr>
        @if(!empty($group['children']))
            @php $hasSubGroups = isset($group['children'][0]['children']); @endphp
            @if($hasSubGroups)
                @foreach($group['children'] as $subGroup)
                    <tr style="background: rgb(150, 150, 150); font-weight: bold;">
                        <td colspan="4" height="14">{{ $subGroup['code'] }}. {{ $subGroup['name'] }}</td>
                    </tr>
                    @foreach($subGroup['children'] as $idx => $row)
                        <tr style="background: {{ $idx % 2 === 0 ? 'rgb(230, 230, 230)' : 'rgb(255, 255, 255)' }};">
                            <td>{{ $row['code'] }}. {{ $row['name'] }}</td>
                            <td align="right">{{ number_format($row['prior'], 2) }}</td>
                            <td align="right">{{ number_format($row['current'], 2) }}</td>
                            <td align="right">{{ number_format($row['ytd'], 2) }}</td>
                        </tr>
                    @endforeach
                    <tr style="background: rgb(150, 150, 150); font-weight: bold;">
                        <td height="14">Jumlah {{ $subGroup['name'] }}</td>
                        <td align="right">{{ number_format($subGroup['prior'], 2) }}</td>
                        <td align="right">{{ number_format($subGroup['current'], 2) }}</td>
                        <td align="right">{{ number_format($subGroup['ytd'], 2) }}</td>
                    </tr>
                @endforeach
            @else
                @foreach($group['children'] as $idx => $row)
                    <tr style="background: {{ $idx % 2 === 0 ? 'rgb(230, 230, 230)' : 'rgb(255, 255, 255)' }};">
                        <td>{{ $row['code'] }}. {{ $row['name'] }}</td>
                        <td align="right">{{ number_format($row['prior'], 2) }}</td>
                        <td align="right">{{ number_format($row['current'], 2) }}</td>
                        <td align="right">{{ number_format($row['ytd'], 2) }}</td>
                    </tr>
                @endforeach
            @endif
        @endif
        <tr style="background: rgb(200, 200, 200); font-weight: bold;">
            <td height="14">Jumlah {{ $group['name'] }}</td>
            <td align="right">{{ number_format($group['prior'], 2) }}</td>
            <td align="right">{{ number_format($group['current'], 2) }}</td>
            <td align="right">{{ number_format($group['ytd'], 2) }}</td>
        </tr>
    @endforeach
    <tr style="background: rgb(200, 200, 200); font-weight: bold;">
        <td>Laba (Rugi) Operasional (A)</td>
        <td align="right">{{ number_format($summary['operating']['prior'], 2) }}</td>
        <td align="right">{{ number_format($summary['operating']['current'], 2) }}</td>
        <td align="right">{{ number_format($summary['operating']['ytd'], 2) }}</td>
    </tr>
    <tr style="background: rgb(200, 200, 200); font-weight: bold;">
        <td>Laba (Rugi) Non Operasional (B)</td>
        <td align="right">{{ number_format($summary['non_operating']['prior'], 2) }}</td>
        <td align="right">{{ number_format($summary['non_operating']['current'], 2) }}</td>
        <td align="right">{{ number_format($summary['non_operating']['ytd'], 2) }}</td>
    </tr>
    <tr style="background: rgb(200, 200, 200); font-weight: bold;">
        <td>Laba (Rugi) Sebelum Pajak (A+B)</td>
        <td align="right">{{ number_format($summary['before_tax']['prior'], 2) }}</td>
        <td align="right">{{ number_format($summary['before_tax']['current'], 2) }}</td>
        <td align="right">{{ number_format($summary['before_tax']['ytd'], 2) }}</td>
    </tr>
    <tr style="background: rgb(200, 200, 200); font-weight: bold;">
        <td>Beban Pajak</td>
        <td align="right">{{ number_format($summary['tax']['prior'], 2) }}</td>
        <td align="right">{{ number_format($summary['tax']['current'], 2) }}</td>
        <td align="right">{{ number_format($summary['tax']['ytd'], 2) }}</td>
    </tr>
    <tr style="background: rgb(200, 200, 200); font-weight: bold;">
        <td>Laba (Rugi) Bersih</td>
        <td align="right">{{ number_format($summary['after_tax']['prior'], 2) }}</td>
        <td align="right">{{ number_format($summary['after_tax']['current'], 2) }}</td>
        <td align="right">{{ number_format($summary['after_tax']['ytd'], 2) }}</td>
    </tr>
</table>
@endsection

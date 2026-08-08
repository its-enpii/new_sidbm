@extends('reports.pdf.layout', ['title' => 'Laporan Arus Kas', 'identity' => $identity, 'period' => $period])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="3" align="center">
            <div style="font-size: 18px;"><b>ARUS KAS</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="3" height="5"></td></tr>
    <tr style="background: rgb(128, 128, 128); font-weight: bold; color: #fff;">
        <td width="5%"></td>
        <td width="80%">Saldo kas awal periode</td>
        <td width="15%" align="right">{{ number_format($opening_cash, 2) }}</td>
    </tr>
    @foreach($sections as $section)
        <tr style="background: rgb(128, 128, 128); font-weight: bold; color: #fff;">
            <td></td>
            <td colspan="2">{{ $section['label'] }}</td>
        </tr>
        @forelse($section['lines'] as $line)
            <tr style="background: {{ $loop->iteration % 2 == 0 ? 'rgb(200, 200, 200)' : 'rgb(240, 240, 240)' }};">
                <td></td>
                <td>
                    {{ $line['label'] }}
                    @if(($line['count'] ?? 1) > 1)
                        <span style="color: grey;">({{ $line['count'] }} jurnal)</span>
                    @endif
                </td>
                <td align="right">{{ number_format($line['amount'], 2) }}</td>
            </tr>
        @empty
            <tr style="background: rgb(240, 240, 240);">
                <td></td>
                <td colspan="2" style="color: grey;">Tidak ada mutasi</td>
            </tr>
        @endforelse
        <tr style="background: rgb(150, 150, 150); font-weight: bold;">
            <td></td>
            <td>Jumlah {{ strtolower($section['label']) }}</td>
            <td align="right">{{ number_format($section['total'], 2) }}</td>
        </tr>
    @endforeach
    <tr style="background: rgb(128, 128, 128); font-weight: bold; color: #fff;">
        <td></td>
        <td>Kenaikan (Penurunan) Kas</td>
        <td align="right">{{ number_format($net_change, 2) }}</td>
    </tr>
    <tr style="background: rgb(128, 128, 128); font-weight: bold; color: #fff;">
        <td></td>
        <td>Saldo kas akhir periode</td>
        <td align="right">{{ number_format($closing_cash, 2) }}</td>
    </tr>
</table>
@endsection

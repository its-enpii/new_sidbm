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
</table>
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr style="background: rgb(200, 200, 200)">
        <th colspan="2">Nama Akun</th>
        <th>Jumlah</th>
    </tr>

    <tr><td colspan="3" height="3"></td></tr>
    <tr style="background: rgb(128, 128, 128)">
        <td width="5%" align="center">I</td>
        <td width="80%">{{ $opening_label ?? ('Saldo Awal per '.date('d/m/Y', strtotime($period['from'] ?? 'now'))) }}</td>
        <td width="15%" align="right">{{ number_format($opening_cash, 2) }}</td>
    </tr>

    @foreach($sections as $section)
        <tr><td colspan="3" height="3"></td></tr>
        <tr style="background: rgb(128, 128, 128)">
            <td width="5%" align="center">{{ $section['roman'] ?? '' }}</td>
            <td width="80%" colspan="2">{{ $section['label'] }}</td>
        </tr>
        @foreach($section['lines'] as $line)
            <tr style="background: {{ $loop->iteration % 2 == 0 ? 'rgb(240, 240, 240)' : 'rgb(200, 200, 200)' }};">
                <td width="5%" align="center">&nbsp;</td>
                <td width="80%">{{ $line['label'] }}</td>
                <td align="right">{{ number_format($line['amount'], 2) }}</td>
            </tr>
        @endforeach
        <tr style="background: rgb(150, 150, 150); font-weight: bold;">
            <td align="center">&nbsp;</td>
            <td>{{ $section['sum_label'] ?? ('Jumlah '.$section['label']) }}</td>
            <td align="right">{{ number_format($section['total'], 2) }}</td>
        </tr>
    @endforeach

    <tr>
        <td colspan="3" style="padding: 0px !important;">
            <table class="p" border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
                <tr style="background: rgb(128, 128, 128)">
                    <td width="5%" align="center">&nbsp;</td>
                    <td width="80%">Kenaikan (Penurunan) Kas</td>
                    <td width="15%" align="right">{{ number_format($net_change, 2) }}</td>
                </tr>
                <tr style="background: rgb(128, 128, 128)">
                    <td align="center">&nbsp;</td>
                    <td>SALDO AKHIR KAS SETARA KAS</td>
                    <td align="right">{{ number_format($closing_cash, 2) }}</td>
                </tr>
            </table>

            <div style="margin-top: 16px;"></div>
            {!! $tanda_tangan ?? '' !!}
        </td>
    </tr>
</table>
@endsection

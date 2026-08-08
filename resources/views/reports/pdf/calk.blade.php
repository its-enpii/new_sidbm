@extends('reports.pdf.layout', ['title' => 'Catatan Atas Laporan Keuangan (CALK)', 'identity' => $identity, 'period' => $period])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="2" align="center">
            <div style="font-size: 18px;"><b>CATATAN ATAS LAPORAN KEUANGAN (CALK)</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="2" height="5"></td></tr>
    <tr style="background: rgb(232, 232, 232); font-weight: bold;">
        <td height="20">Ringkasan posisi</td>
        <td align="right">Jumlah</td>
    </tr>
    @foreach($highlights as $h)
        <tr style="background: {{ $loop->iteration % 2 == 1 ? 'rgb(230, 230, 230)' : 'rgba(255, 255, 255)' }};">
            <td>{{ $h['label'] }}</td>
            <td align="right">
                @if(($h['amount'] ?? 0) < 0)
                    ({{ number_format(abs($h['amount']), 2) }})
                @else
                    {{ number_format($h['amount'], 2) }}
                @endif
            </td>
        </tr>
    @endforeach
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-top: 14px;">
    <tr>
        <td style="font-weight: bold; font-size: 12px; padding-bottom: 4px;">Kebijakan akuntansi</td>
    </tr>
    <tr>
        <td>
            <ol style="margin: 0 0 12px 18px; padding: 0;">
                @foreach($policies as $p)
                    <li style="margin-bottom: 4px;">{{ $p }}</li>
                @endforeach
            </ol>
        </td>
    </tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-top: 10px;">
    <tr>
        <td style="font-weight: bold; font-size: 12px; padding-bottom: 4px;">Catatan tambahan</td>
    </tr>
    <tr>
        <td>
            <div style="white-space: pre-wrap; border: 1px solid #999; padding: 8px; min-height: 60px;">{{ $notes !== '' ? $notes : 'Tidak ada catatan tambahan.' }}</div>
        </td>
    </tr>
</table>
@endsection
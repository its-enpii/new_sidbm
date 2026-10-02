@extends('reports.pdf.layout')

@section('content')
    @foreach ($products as $idx => $prod)
        @if ($idx > 0)
            <div class="break"></div>
        @endif

        @php
            $k1 = $prod['totals']['kolek1_lancar'] ?? 0;
            $k2 = $prod['totals']['kolek2_diragukan'] ?? 0;
            $k3 = $prod['totals']['kolek3_macet'] ?? 0;
            $totalSaldo = $k1 + $k2 + $k3;
            $npl = $totalSaldo > 0 ? round((($k2 + $k3) / $totalSaldo) * 100, 2) : 0;
        @endphp

        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
            <tr>
                <td colspan="3" align="center">
                    <div style="font-size: 18px;">
                        <b>Cadangan Penyisihan Penghapusan {{ $prod['product_name'] }}</b>
                    </div>
                    <div style="font-size: 16px;">
                        <b>{{ strtoupper($period_label) }}</b>
                    </div>
                </td>
            </tr>
            <tr><td colspan="3" height="5"></td></tr>
        </table>

        <table border="1" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
            <tr>
                <th height="20" width="10">No</th>
                <th width="200">Tingkat Kolektibilitas</th>
                <th width="30">%</th>
                <th width="150">Saldo Pinjaman</th>
                <th>Beban Penyisihan Penghapusan Pinjaman</th>
                <th width="150">NPL</th>
            </tr>
            <tr>
                <td align="center">a</td>
                <td align="center">b</td>
                <td align="center">c</td>
                <td align="center">d</td>
                <td align="center">e = c * d</td>
                <td align="center">f = (2 + 3) / saldo</td>
            </tr>
            <tr>
                <td align="center">1</td>
                <td>Lancar</td>
                <td align="center">0%</td>
                <td align="right">{{ number_format($k1) }}</td>
                <td align="right">{{ number_format(($k1 * 0) / 100) }}</td>
                <td align="center" rowspan="4">{{ $npl }}%</td>
            </tr>
            <tr>
                <td align="center">2</td>
                <td>Diragukan</td>
                <td align="center">50%</td>
                <td align="right">{{ number_format($k2) }}</td>
                <td align="right">{{ number_format(($k2 * 50) / 100) }}</td>
            </tr>
            <tr>
                <td align="center">3</td>
                <td>Macet</td>
                <td align="center">100%</td>
                <td align="right">{{ number_format($k3) }}</td>
                <td align="right">{{ number_format(($k3 * 100) / 100) }}</td>
            </tr>
            <tr>
                <th colspan="3" height="15">Total</th>
                <th>{{ number_format($k1 + $k2 + $k3) }}</th>
                <th>{{ number_format(($k1 * 0) / 100 + ($k2 * 50) / 100 + ($k3 * 100) / 100) }}</th>
            </tr>
        </table>

        <div style="margin-top: 16px;"></div>
        {!! $tanda_tangan ?? '' !!}
    @endforeach
@endsection

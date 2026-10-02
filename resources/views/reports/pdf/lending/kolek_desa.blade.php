@extends('reports.pdf.layout')

@section('content')
    <style>
        html { margin-left: 40px; margin-right: 40px; }
        .num { text-align: right; white-space: nowrap; }
    </style>

    @foreach ($products as $idx => $prod)
        @if ($idx > 0)
            <div class="break"></div>
        @endif

        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
            <tr>
                <td colspan="3" align="center">
                    <div style="font-size: 18px;">
                        <b>DAFTAR KOLEKTIBILITAS REKAP DESA {{ strtoupper($prod['product_name']) }}</b>
                    </div>
                    <div style="font-size: 16px;">
                        <b>{{ strtoupper($period_label) }}</b>
                    </div>
                </td>
            </tr>
            <tr><td colspan="3" height="5"></td></tr>
        </table>

        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; table-layout: fixed;">
            <thead>
                <tr>
                    <th class="t l b" rowspan="2" width="24%">Nama Desa</th>
                    <th class="t l b" rowspan="2" width="10%">Alokasi</th>
                    <th class="t l b" rowspan="2" width="10%">Saldo</th>
                    <th class="t l b" rowspan="2" width="4%">%</th>
                    <th class="t l b" colspan="2" width="20%">Tunggakan</th>
                    <th class="t l b" width="10%">Lancar</th>
                    <th class="t l b" width="10%">Diragukan</th>
                    <th class="t l b r" width="10%">Macet</th>
                </tr>
                <tr>
                    <th class="t l b" width="10%">Pokok</th>
                    <th class="t l b" width="10%">Jasa</th>
                    <th class="t l b">(Menunggak 1-3)</th>
                    <th class="t l b">(Menunggak 4-5)</th>
                    <th class="t l b r">(Menunggak 6+)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($prod['villages'] as $v)
                    @php
                        $pross = $v['alokasi'] > 0 ? $v['saldo'] / $v['alokasi'] : 0;
                    @endphp
                    <tr>
                        <td class="l b">{{ $loop->iteration }}. {{ $v['village_name'] }}</td>
                        <td class="l b num">{{ number_format($v['alokasi']) }}</td>
                        <td class="l b num">{{ number_format($v['saldo']) }}</td>
                        <td class="l b" align="center">{{ number_format(floor($pross * 100)) }}</td>
                        <td class="l b num">{{ number_format($v['tunggakan_pokok']) }}</td>
                        <td class="l b num">{{ number_format($v['tunggakan_jasa']) }}</td>
                        <td class="l b num">{{ number_format($v['kolek1_lancar']) }}</td>
                        <td class="l b num">{{ number_format($v['kolek2_diragukan']) }}</td>
                        <td class="l b r num">{{ number_format($v['kolek3_macet']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                @php
                    $tAlokasi = $prod['totals']['alokasi'] ?? 0;
                    $tSaldo = $prod['totals']['saldo'] ?? 0;
                    $tPros = $tAlokasi > 0 ? $tSaldo / $tAlokasi : 0;
                    $tK1 = $prod['totals']['kolek1_lancar'] ?? 0;
                    $tK2 = $prod['totals']['kolek2_diragukan'] ?? 0;
                    $tK3 = $prod['totals']['kolek3_macet'] ?? 0;
                @endphp
                <tr>
                    <td colspan="9" style="padding: 0px !important;">
                        <table class="p" border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; table-layout: fixed;">
                            <tr style="background: rgb(232,232,232); font-weight: bold;">
                                <td class="t l b" width="24%" align="center" height="20">J U M L A H</td>
                                <td class="t l b" width="10%" align="right">{{ number_format($tAlokasi) }}</td>
                                <td class="t l b" width="10%" align="right">{{ number_format($tSaldo) }}</td>
                                <td class="t l b" width="4%" align="center">{{ number_format(floor($tPros * 100)) }}</td>
                                <td class="t l b" width="10%" align="right">{{ number_format($prod['totals']['tunggakan_pokok'] ?? 0) }}</td>
                                <td class="t l b" width="10%" align="right">{{ number_format($prod['totals']['tunggakan_jasa'] ?? 0) }}</td>
                                <td class="t l b" width="10%" align="right">{{ number_format($tK1) }}</td>
                                <td class="t l b" width="10%" align="right">{{ number_format($tK2) }}</td>
                                <td class="t l b r" width="10%" align="right">{{ number_format($tK3) }}</td>
                            </tr>
                            <tr style="background: rgb(232,232,232); font-weight: bold;">
                                <td class="t l b" align="center" rowspan="2" height="20">Resiko Pinjaman</td>
                                <td class="t l b" colspan="5" align="center">(Lancar + Diragukan + Macet)</td>
                                <td class="t l b" align="center">Lancar * 0%</td>
                                <td class="t l b" align="center">Diragukan * 50%</td>
                                <td class="t l b r" align="center">Macet * 100%</td>
                            </tr>
                            <tr style="background: rgb(232,232,232); font-weight: bold;">
                                <td class="t l b" align="center" colspan="5">
                                    {{ number_format(($tK1 * 0) / 100 + ($tK2 * 50) / 100 + ($tK3 * 100) / 100) }}
                                </td>
                                <td class="t l b" align="center">{{ number_format(($tK1 * 0) / 100) }}</td>
                                <td class="t l b" align="center">{{ number_format(($tK2 * 50) / 100) }}</td>
                                <td class="t l b r" align="center">{{ number_format(($tK3 * 100) / 100) }}</td>
                            </tr>
                            <tr>
                                <td colspan="9">
                                    <div style="margin-top: 16px;"></div>
                                    {!! $tanda_tangan ?? '' !!}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </tfoot>
        </table>
    @endforeach
@endsection

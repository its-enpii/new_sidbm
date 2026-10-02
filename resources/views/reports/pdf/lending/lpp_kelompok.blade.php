@extends('reports.pdf.layout')

@section('content')
    <style>
        html { margin-left: 40px; margin-right: 40px; }
    </style>

    @foreach ($products as $idx => $prod)
        @if ($idx > 0)
            <div class="break"></div>
        @endif

        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
            <tr>
                <td colspan="3" align="center">
                    <div style="font-size: 18px;">
                        <b>
                            DAFTAR PERKEMBANGAN PIUTANG PER KELOMPOK
                            {{ strtoupper($prod['product_name']) }}
                        </b>
                    </div>
                    <div style="font-size: 16px;">
                        <b>{{ strtoupper($period_label) }}</b>
                    </div>
                </td>
            </tr>
            <tr><td colspan="3" height="5"></td></tr>
        </table>

        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 8px; table-layout: fixed;">
            <thead>
                <tr style="background: rgb(230, 230, 230); font-weight: bold;">
                    <th class="t l b" rowspan="2" width="2%">No</th>
                    <th class="t l b" rowspan="2">Kelompok - Loan ID</th>
                    <th class="t l b" rowspan="2" width="4%">
                        <div>Tgl Cair</div>
                        <div><small>(dd/mm/yy)</small></div>
                    </th>
                    <th class="t l b" rowspan="2" width="3%">Jasa</th>
                    <th class="t l b" rowspan="2" width="6%">Alokasi</th>
                    <th class="t l b" colspan="2">Target</th>
                    <th class="t l b" colspan="2">Real s.d. Lalu</th>
                    <th class="t l b" colspan="2">Real Ini</th>
                    <th class="t l b" colspan="2">Real s.d. Ini</th>
                    <th class="t l b" colspan="2">Saldo</th>
                    <th class="t l b" rowspan="2" width="2%">%</th>
                    <th class="t l b r" colspan="2">Tunggakan</th>
                </tr>
                <tr style="background: rgb(230, 230, 230); font-weight: bold;">
                    <th class="t l b" width="6%">Pokok</th>
                    <th class="t l b" width="6%">Jasa</th>
                    <th class="t l b" width="6%">Pokok</th>
                    <th class="t l b" width="6%">Jasa</th>
                    <th class="t l b" width="6%">Pokok</th>
                    <th class="t l b" width="6%">Jasa</th>
                    <th class="t l b" width="6%">Pokok</th>
                    <th class="t l b" width="6%">Jasa</th>
                    <th class="t l b" width="6%">Pokok</th>
                    <th class="t l b" width="6%">Jasa</th>
                    <th class="t l b" width="6%">Pokok</th>
                    <th class="t l b r" width="6%">Jasa</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($prod['villages'] as $v)
                    <tr style="font-weight: bold;">
                        <td class="t l b r" colspan="18" align="left">
                            {{ $v['village_code'] ?? '' }}. {{ $v['village_name'] }}
                        </td>
                    </tr>

                    @foreach ($v['loans'] as $loan)
                        @php
                            $jangka = (int) ($loan['jangka'] ?? 0);
                            $prosJasa = ($loan['pros_jasa'] ?? 0) == 0 || $jangka == 0
                                ? 0
                                : ($loan['pros_jasa'] / $jangka);
                            $prossSaldo = ($loan['alokasi'] ?? 0) > 0 ? ($loan['saldo_pokok'] / $loan['alokasi']) : 0;
                            $tglLunas = $loan['tgl_lunas'] ?? null;
                            $statusLunas = $tglLunas !== null && $tglLunas <= $period_end;
                        @endphp
                        <tr>
                            <td class="t l b" align="center">{{ $loop->iteration }}</td>
                            <td class="t l b" align="left">
                                {{ $loan['group_name'] }} [{{ $loan['ketua'] ?? '' }}] - {{ $loan['loan_id'] }}
                            </td>
                            <td class="t l b" align="center">
                                {{ $loan['disbursed_at'] ? date('d/m/y', strtotime($loan['disbursed_at'])) : '' }}
                            </td>
                            <td class="t l b" align="center">
                                <small>{{ $jangka }}*{{ number_format($prosJasa, 2) }}</small>
                            </td>
                            <td class="t l b" align="right">{{ number_format($loan['alokasi']) }}</td>
                            <td class="t l b" align="right">{{ number_format($loan['target_pokok']) }}</td>
                            <td class="t l b" align="right">{{ number_format($loan['target_jasa']) }}</td>
                            <td class="t l b" align="right">{{ number_format($loan['real_lalu_pokok']) }}</td>
                            <td class="t l b" align="right">{{ number_format($loan['real_lalu_jasa']) }}</td>
                            <td class="t l b" align="right">{{ number_format($loan['real_ini_pokok']) }}</td>
                            <td class="t l b" align="right">{{ number_format($loan['real_ini_jasa']) }}</td>
                            <td class="t l b" align="right">{{ number_format($loan['real_kumulatif_pokok']) }}</td>
                            <td class="t l b" align="right">{{ number_format($loan['real_kumulatif_jasa']) }}</td>
                            <td class="t l b" align="right">{{ number_format($loan['saldo_pokok']) }}</td>
                            <td class="t l b" align="right">{{ number_format($loan['saldo_jasa']) }}</td>
                            <td class="t l b" align="center">{{ number_format(floor($prossSaldo * 100)) }}</td>

                            @if ($statusLunas && ($loan['status'] ?? '') === 'paid')
                                <td class="t l b r" colspan="2" align="center">V-LUNAS {{ $tglLunas }}</td>
                            @elseif ($statusLunas && ($loan['status'] ?? '') === 'rescheduled')
                                <td class="t l b r" colspan="2" align="center">Rescedulling {{ $tglLunas }}</td>
                            @elseif ($statusLunas && ($loan['status'] ?? '') === 'written_off')
                                <td class="t l b r" colspan="2" align="center">Penghapusan {{ $tglLunas }}</td>
                            @else
                                <td class="t l b" align="right">{{ number_format($loan['tunggakan_pokok']) }}</td>
                                <td class="t l b r" align="right">{{ number_format($loan['tunggakan_jasa']) }}</td>
                            @endif
                        </tr>
                    @endforeach

                    <tr style="font-weight: bold;">
                        <td class="t l b" colspan="4" align="left" height="15">Jumlah {{ $v['village_name'] }}</td>
                        <td class="t l b" align="right">{{ number_format($v['subtotal']['alokasi']) }}</td>
                        <td class="t l b" align="right">{{ number_format($v['subtotal']['target_pokok']) }}</td>
                        <td class="t l b" align="right">{{ number_format($v['subtotal']['target_jasa']) }}</td>
                        <td class="t l b" align="right">{{ number_format($v['subtotal']['real_lalu_pokok']) }}</td>
                        <td class="t l b" align="right">{{ number_format($v['subtotal']['real_lalu_jasa']) }}</td>
                        <td class="t l b" align="right">{{ number_format($v['subtotal']['real_ini_pokok']) }}</td>
                        <td class="t l b" align="right">{{ number_format($v['subtotal']['real_ini_jasa']) }}</td>
                        <td class="t l b" align="right">{{ number_format($v['subtotal']['real_kumulatif_pokok']) }}</td>
                        <td class="t l b" align="right">{{ number_format($v['subtotal']['real_kumulatif_jasa']) }}</td>
                        <td class="t l b" align="right">{{ number_format($v['subtotal']['saldo_pokok']) }}</td>
                        <td class="t l b" align="right">{{ number_format($v['subtotal']['saldo_jasa']) }}</td>
                        @php
                            $subPross = ($v['subtotal']['target_pokok'] ?? 0) != 0
                                ? ($v['subtotal']['real_kumulatif_pokok'] / $v['subtotal']['target_pokok'])
                                : 1;
                        @endphp
                        <td class="t l b" align="center">{{ number_format(floor($subPross * 100)) }}</td>
                        <td class="t l b" align="right">{{ number_format($v['subtotal']['tunggakan_pokok']) }}</td>
                        <td class="t l b r" align="right">{{ number_format($v['subtotal']['tunggakan_jasa']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                @php
                    $t = $prod['totals'];
                    $tPross = ($t['target_pokok'] ?? 0) != 0
                        ? ($t['real_kumulatif_pokok'] / $t['target_pokok'])
                        : 1;
                @endphp
                <tr style="font-weight: bold;">
                    <td class="t l b" align="left" height="15" colspan="4">Aktif s.d. {{ $period_end }}</td>
                    <td class="t l b" align="right">{{ number_format($t['alokasi']) }}</td>
                    <td class="t l b" align="right">{{ number_format($t['target_pokok']) }}</td>
                    <td class="t l b" align="right">{{ number_format($t['target_jasa']) }}</td>
                    <td class="t l b" align="right">{{ number_format($t['real_lalu_pokok']) }}</td>
                    <td class="t l b" align="right">{{ number_format($t['real_lalu_jasa']) }}</td>
                    <td class="t l b" align="right">{{ number_format($t['real_ini_pokok']) }}</td>
                    <td class="t l b" align="right">{{ number_format($t['real_ini_jasa']) }}</td>
                    <td class="t l b" align="right">{{ number_format($t['real_kumulatif_pokok']) }}</td>
                    <td class="t l b" align="right">{{ number_format($t['real_kumulatif_jasa']) }}</td>
                    <td class="t l b" align="right">{{ number_format($t['saldo_pokok']) }}</td>
                    <td class="t l b" align="right">{{ number_format($t['saldo_jasa']) }}</td>
                    <td class="t l b" align="center">{{ number_format(floor($tPross * 100)) }}</td>
                    <td class="t l b" align="right">{{ number_format($t['tunggakan_pokok']) }}</td>
                    <td class="t l b r" align="right">{{ number_format($t['tunggakan_jasa']) }}</td>
                </tr>

                <tr>
                    <td colspan="18" style="padding: 0px !important;">
                        <table class="p" border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 8px; table-layout: fixed;">
                            <tr style="background: rgb(230, 230, 230); font-weight: bold;">
                                <td class="t l b" align="center" height="15">J U M L A H</td>
                                <td class="t l b" width="6%" align="right">{{ number_format($t['alokasi']) }}</td>
                                <td class="t l b" width="6%" align="right">{{ number_format($t['target_pokok']) }}</td>
                                <td class="t l b" width="6%" align="right">{{ number_format($t['target_jasa']) }}</td>
                                <td class="t l b" width="6%" align="right">{{ number_format($t['real_lalu_pokok']) }}</td>
                                <td class="t l b" width="6%" align="right">{{ number_format($t['real_lalu_jasa']) }}</td>
                                <td class="t l b" width="6%" align="right">{{ number_format($t['real_ini_pokok']) }}</td>
                                <td class="t l b" width="6%" align="right">{{ number_format($t['real_ini_jasa']) }}</td>
                                <td class="t l b" width="6%" align="right">{{ number_format($t['real_kumulatif_pokok']) }}</td>
                                <td class="t l b" width="6%" align="right">{{ number_format($t['real_kumulatif_jasa']) }}</td>
                                <td class="t l b" width="6%" align="right">{{ number_format($t['saldo_pokok']) }}</td>
                                <td class="t l b" width="6%" align="right">{{ number_format($t['saldo_jasa']) }}</td>
                                <td class="t l b" width="2%" align="center">{{ number_format(floor($tPross * 100)) }}</td>
                                <td class="t l b" width="6%" align="right">{{ number_format($t['tunggakan_pokok']) }}</td>
                                <td class="t l b r" width="6%" align="right">{{ number_format($t['tunggakan_jasa']) }}</td>
                            </tr>
                            <tr>
                                <td colspan="15">
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

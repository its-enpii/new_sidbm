@extends('reports.pdf.layout', ['title' => 'Laporan Laba Rugi', 'identity' => $identity, 'period' => $period])

@section('content')
@php
    $byBucket = [];
    foreach ($groups as $g) {
        $byBucket[$g['bucket'] ?? ''][] = $g;
    }
    $renderGroups = function (array $list) {
        return $list;
    };
@endphp
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="4" align="center">
            <div style="font-size: 18px;"><b>LAPORAN LABA RUGI</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="4" height="5"></td></tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <thead>
        <tr style="background: rgb(232, 232, 232); font-weight: bold; font-size: 12px;">
            <td align="center" width="55%" height="16">Rekening</td>
            <td align="center" width="15%">s.d. {{ $header_lalu ?? '' }}</td>
            <td align="center" width="15%">{{ $header_sekarang ?? '' }}</td>
            <td align="center" width="15%">s.d. {{ $header_sekarang ?? '' }}</td>
        </tr>
    </thead>

    <tbody>
        <tr style="background: rgb(200, 200, 200); font-weight: bold; text-transform: uppercase;">
            <td colspan="4" height="14">4. Pendapatan</td>
        </tr>

        @foreach ($byBucket['revenue_ops'] ?? [] as $group)
            @include('reports.pdf.partials.income_group', ['group' => $group, 'bg' => '150, 150, 150'])
        @endforeach

        @foreach ($byBucket['expense_ops'] ?? [] as $group)
            @include('reports.pdf.partials.income_group', ['group' => $group, 'bg' => '150, 150, 150'])
        @endforeach

        <tr style="background: rgb(200, 200, 200); font-weight: bold;">
            <td align="left">A. Laba Rugi OPERASIONAL (Kode Akun 4.1 - 5.1 - 5.2) </td>
            <td align="right">{{ number_format($summary['operating']['prior'], 2) }}</td>
            <td align="right">{{ number_format($summary['operating']['current'], 2) }}</td>
            <td align="right">{{ number_format($summary['operating']['ytd'], 2) }}</td>
        </tr>

        <tr><td colspan="4" height="2"></td></tr>

        @foreach ($byBucket['revenue_non'] ?? [] as $group)
            @include('reports.pdf.partials.income_group', ['group' => $group, 'bg' => '150, 150, 150'])
        @endforeach

        @foreach ($byBucket['expense_non'] ?? [] as $group)
            @include('reports.pdf.partials.income_group', ['group' => $group, 'bg' => '150, 150, 150'])
        @endforeach

        <tr style="background: rgb(200, 200, 200); font-weight: bold;">
            <td align="left">B. Laba Rugi NON OPERASIONAL (Kode Akun 4.2 - 5.3) </td>
            <td align="right">{{ number_format($summary['non_operating']['prior'], 2) }}</td>
            <td align="right">{{ number_format($summary['non_operating']['current'], 2) }}</td>
            <td align="right">{{ number_format($summary['non_operating']['ytd'], 2) }}</td>
        </tr>

        <tr><td colspan="4" height="2"></td></tr>

        <tr style="background: rgb(200, 200, 200); font-weight: bold;">
            <td align="left">C. Laba Rugi Sebelum Taksiran Pajak (A + B) </td>
            <td align="right">{{ number_format($summary['before_tax']['prior'], 2) }}</td>
            <td align="right">{{ number_format($summary['before_tax']['current'], 2) }}</td>
            <td align="right">{{ number_format($summary['before_tax']['ytd'], 2) }}</td>
        </tr>

        <tr><td colspan="4" height="2"></td></tr>

        <tr style="background: rgb(150, 150, 150); font-weight: bold;">
            <td colspan="4" height="14">5.4 Beban Pajak</td>
        </tr>
        <tr style="background: rgb(230, 230, 230);">
            <td align="left">5.4.01.01. Taksiran PPh</td>
            <td align="right">{{ number_format($summary['tax']['prior'], 2) }}</td>
            <td align="right">{{ number_format($summary['tax']['current'], 2) }}</td>
            <td align="right">{{ number_format($summary['tax']['ytd'], 2) }}</td>
        </tr>

        <tr><td colspan="4" height="2"></td></tr>

        <tr>
            <td colspan="4" style="padding: 0px !important;">
                <table class="p" border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
                    <tr style="background: rgb(200, 200, 200); font-weight: bold;">
                        <td width="55%" align="left">C. Laba Rugi Setelah Taksiran Pajak (A + B) </td>
                        <td width="15%" align="right">{{ number_format($summary['after_tax']['prior'], 2) }}</td>
                        <td width="15%" align="right">{{ number_format($summary['after_tax']['current'], 2) }}</td>
                        <td width="15%" align="right">{{ number_format($summary['after_tax']['ytd'], 2) }}</td>
                    </tr>
                </table>

                <div style="margin-top: 16px;"></div>
                {!! $tanda_tangan ?? '' !!}
            </td>
        </tr>
    </tbody>
</table>
@endsection

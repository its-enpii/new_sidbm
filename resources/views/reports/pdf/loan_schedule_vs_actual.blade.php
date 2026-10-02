@extends('reports.pdf.layout', ['title' => 'Laporan Realisasi Pencairan Kelompok', 'identity' => $identity, 'period' => $period])

@section('content')
@php
    $productsList = $products ?? [];
    if ($productsList === [] && ! empty($villages)) {
        // Fallback: bungkus seluruh desa sebagai satu blok produk.
        $productsList = [[
            'product_code' => 'ALL',
            'product_name' => 'Semua Produk',
            'villages' => $villages,
            'totals' => $pencairan_totals ?? ['kelompok' => 0, 'pemanfaat' => 0, 'pengajuan' => 0, 'pencairan' => 0],
        ]];
    }
@endphp

@foreach ($productsList as $pIdx => $product)
    @if ($pIdx > 0)
        <div class="break"></div>
    @endif

    @php
        $productVillages = $product['villages'] ?? [];
        $productTotals = $product['totals'] ?? ['kelompok' => 0, 'pemanfaat' => 0, 'pengajuan' => 0, 'pencairan' => 0];
    @endphp

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 10px;">
        <tr>
            <td colspan="3" align="center">
                <div style="font-size: 18px;"><b>LAPORAN REALISASI PENCAIRAN KELOMPOK</b></div>
                <div style="font-size: 14px;"><b>{{ strtoupper($product['product_name'] ?? '') }}</b></div>
                <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
            </td>
        </tr>
        <tr><td colspan="3" height="5"></td></tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
        <thead>
            <tr>
                <th class="t l b" rowspan="2" width="5%">No</th>
                <th class="t l b" rowspan="2" width="23%">Kelompok - Load ID</th>
                <th class="t l b" rowspan="2" width="20%">Nomor SPK</th>
                <th class="t l b" rowspan="2" width="12%">Ketua Kelompok</th>
                <th class="t l b" rowspan="2" width="5%">Ang</th>
                <th class="t l b" rowspan="2" width="8%">Tgl Cair</th>
                <th class="t l b" rowspan="2" width="5%">T/S</th>
                <th class="t l b r" colspan="2" width="22%">Alokasi</th>
            </tr>
            <tr>
                <th class="t l b" width="11%">Pengajuan</th>
                <th class="t l b r" width="11%">Pencairan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($productVillages as $village)
                <tr style="font-weight: bold;">
                    <td class="t l b r" colspan="9" align="left">
                        {{ $village['kode_desa'] }}. {{ $village['nama_desa'] }}
                    </td>
                </tr>
                @foreach ($village['loans'] as $loan)
                    <tr>
                        <td class="t l b" align="center">{{ $loop->iteration }}</td>
                        <td class="t l b">{{ $loan['group_name'] }} - {{ $loan['loan_id'] }}</td>
                        <td class="t l b">{{ $loan['spk_no'] ?? $loan['loan_number'] }}</td>
                        <td class="t l b">{{ $loan['ketua'] }}</td>
                        <td class="t l b" align="center">{{ $loan['pinjaman_anggota_count'] ?? $loan['pemanfaat_count'] }}</td>
                        <td class="t l b" align="center">{{ $loan['disbursed_at'] ? date('d/m/y', strtotime($loan['disbursed_at'])) : '' }}</td>
                        <td class="t l b" align="center">{{ $loan['jangka'] }}/{{ $loan['sistem_pokok'] }}</td>
                        <td class="t l b" align="right">{{ number_format($loan['pengajuan'] ?? $loan['proposal']) }}</td>
                        <td class="t l b r" align="right">{{ number_format($loan['pencairan'] ?? $loan['alokasi']) }}</td>
                    </tr>
                @endforeach
                <tr style="font-weight: bold;">
                    <td class="t l b" colspan="4" align="center" height="15">
                        Jumlah Kelompok {{ $village['nama_desa'] }} ({{ $village['subtotal']['kelompok'] }})
                    </td>
                    <td class="t l b" align="center">{{ $village['subtotal']['pemanfaat'] }}</td>
                    <td class="t l b" align="right" colspan="2">&nbsp;</td>
                    <td class="t l b" align="right">{{ number_format($village['subtotal']['pengajuan']) }}</td>
                    <td class="t l b r" align="right">{{ number_format($village['subtotal']['pencairan']) }}</td>
                </tr>
            @endforeach

            @if (count($productVillages) > 0)
                <tr>
                    <td colspan="9" style="padding: 0px !important;">
                        <table class="p" border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; table-layout: fixed;">
                            <tr style="font-weight: bold;">
                                <td class="t l b" colspan="4" align="center" height="15" width="60%">
                                    J U M L A H ({{ $productTotals['kelompok'] }})
                                </td>
                                <td class="t l b" align="center" width="5%">{{ $productTotals['pemanfaat'] }}</td>
                                <td class="t l b" align="right" width="13%">&nbsp;</td>
                                <td class="t l b" align="right" width="11%">{{ number_format($productTotals['pengajuan']) }}</td>
                                <td class="t l b r" align="right" width="11%">{{ number_format($productTotals['pencairan']) }}</td>
                            </tr>
                            <tr>
                                <td colspan="8">
                                    <div style="margin-top: 16px;"></div>
                                    {!! $tanda_tangan ?? '' !!}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
@endforeach
@endsection

@extends('reports.pdf.layout')

@section('content')
    <style>
        html { margin-left: 40px; margin-right: 40px; }
        .num { text-align: right; white-space: nowrap; }
    </style>

    @php $productIdx = 0; @endphp
    @foreach ($products as $product)
        @php
            $productLoans = [];
            foreach ($villages as $village) {
                $loans = array_values(array_filter(
                    $village['loans'],
                    fn ($l) => ($l['product_code'] ?? null) === $product['product_code'],
                ));
                if ($loans !== []) {
                    $productLoans[] = ['village' => $village, 'loans' => $loans];
                }
            }
        @endphp
        @if ($productLoans === [])
            @continue
        @endif

        @if ($productIdx > 0)
            <div class="break"></div>
        @endif
        @php $productIdx++; @endphp

        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
            <tr>
                <td colspan="3" align="center">
                    <div style="font-size: 18px;">
                        <b>DAFTAR PINJAMAN TIDAK LAYAK {{ strtoupper($product['product_name']) }}</b>
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
                <tr style="background: rgb(230, 230, 230); font-weight: bold;">
                    <th class="t l b" width="5%" height="20">No</th>
                    <th class="t l b" width="25%">Nama Kelompok</th>
                    <th class="t l b" width="30%">Alamat</th>
                    <th class="t l b" width="20%">Tanggal Tunggu</th>
                    <th class="t l b r" width="20%">Alokasi</th>
                </tr>
            </thead>
            <tbody>
                @php $nomor = 1; $totalAlokasi = 0; @endphp
                @foreach ($productLoans as $block)
                    <tr style="font-weight: bold;">
                        <td class="t l b r" colspan="5" align="left" height="15">
                            {{ $block['village']['kode_desa'] }}. {{ $block['village']['nama_desa'] }}
                        </td>
                    </tr>
                    @foreach ($block['loans'] as $loan)
                        @php
                            $totalAlokasi += $loan['amount'];
                            $jenisPinjaman = 'Kelompok';
                        @endphp
                        <tr>
                            <td class="t l b" align="center">{{ $nomor++ }}</td>
                            <td class="t l b" align="left">
                                {{ $jenisPinjaman }} {{ $loan['group_name'] }} - {{ $loan['loan_id'] }}
                            </td>
                            <td class="t l b" align="left">{{ $loan['village_name'] }}</td>
                            <td class="t l b" align="center">{{ $loan['unfeasible_at'] ?? '' }}</td>
                            <td class="t l b r" align="right">{{ number_format($loan['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                @endforeach

                @if ($productLoans !== [])
                    <tr>
                        <td colspan="5" style="padding: 0px !important;">
                            <table class="p" border="0" width="100%" cellspacing="0" cellpadding="0" style="table-layout: fixed;">
                                <tr style="background: rgb(230, 230, 230); font-weight: bold;">
                                    <td class="t l b" align="center" width="80%" height="15">J U M L A H</td>
                                    <td class="t l b r" width="20%" align="right">{{ number_format($totalAlokasi, 2) }}</td>
                                </tr>
                                <tr>
                                    <td colspan="2">
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

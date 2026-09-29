@extends('reports.pdf.layout')

@section('content')
    <style>
        html { margin-left: 40px; margin-right: 40px; }
        .num { text-align: right; white-space: nowrap; }
    </style>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px; margin-bottom: 10px;">
        <tr>
            <td align="center">
                <div style="font-size: 18px; font-weight: bold;">
                    DAFTAR PINJAMAN TIDAK LAYAK (KELOMPOK)
                </div>
                <div style="font-size: 16px; font-weight: bold;">
                    PERIODE: {{ strtoupper($period_label) }}
                </div>
            </td>
        </tr>
    </table>

    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 9px; table-layout: fixed;">
        <thead>
            <tr style="background: rgb(230, 230, 230); font-weight: bold; text-align: center;">
                <th class="t l b" width="4%">No</th>
                <th class="t l b" width="26%">Nama Kelompok</th>
                <th class="t l b" width="22%">Desa / Alamat</th>
                <th class="t l b" width="18%">Tgl Ditetapkan / Tunggu</th>
                <th class="t l b" width="18%">Alokasi Pinjaman</th>
                <th class="t l b r" width="12%">Jml Anggota</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($villages as $village)
                <tr style="background: rgb(244, 244, 244); font-weight: bold;">
                    <td class="l b" colspan="6">DESA: {{ strtoupper($village['nama_desa']) }} ({{ $village['kode_desa'] }})</td>
                </tr>
                @foreach ($village['loans'] as $i => $loan)
                    <tr>
                        <td class="l b" align="center">{{ $i + 1 }}</td>
                        <td class="l b">{{ $loan['group_name'] }}</td>
                        <td class="l b">{{ $loan['group_address'] }}</td>
                        <td class="l b" align="center">{{ $loan['unfeasible_at'] ?? $loan['waiting_since'] ?? '-' }}</td>
                        <td class="l b num">{{ number_format($loan['amount'], 0, ',', '.') }}</td>
                        <td class="l b r" align="center">{{ $loan['members_count'] }}</td>
                    </tr>
                @endforeach
                <tr style="background: rgb(238, 238, 238); font-weight: bold;">
                    <td class="l b" colspan="4" align="left">SUBTOTAL {{ strtoupper($village['nama_desa']) }}</td>
                    <td class="l b num">{{ number_format($village['subtotal']['amount'], 0, ',', '.') }}</td>
                    <td class="l b r" align="center">{{ $village['subtotal']['members_count'] }}</td>
                </tr>
            @empty
                <tr>
                    <td class="l b r" colspan="6" align="center" style="padding: 12px;">
                        Tidak ada pinjaman tidak layak pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: rgb(232, 232, 232); font-weight: bold;">
                <th class="t l b" colspan="4" align="left">GRAND TOTAL ({{ $totals['groups_count'] }} Kelompok)</th>
                <th class="t l b num">{{ number_format($totals['amount'], 0, ',', '.') }}</th>
                <th class="t l b r" align="center">{{ $totals['members_count'] }}</th>
            </tr>
        </tfoot>
    </table>
@endsection

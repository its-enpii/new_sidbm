@php
    $orgName = $identity['short_name'] ?? $identity['legal_name'] ?? config('app.name');
    $district = $identity['district_name'] ?? '';
    $year = $period['year'] ?? ($period['fiscal_year'] ?? date('Y'));
    $summary = $accounting_summary ?? ['sections' => [], 'total_asset' => 0, 'total_liability_equity' => 0, 'difference' => 0];
@endphp
@extends('reports.pdf.layout', ['title' => 'Catatan Atas Laporan Keuangan (CALK)', 'identity' => $identity, 'period' => $period])

@section('content')
<style>
    ol, ul { margin-left: unset; }
    .pointA *:first-child { margin-top: 0; }
</style>
<table border="0" width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <td colspan="3" align="center">
            <div style="font-size: 18px;"><b>CATATAN ATAS LAPORAN KEUANGAN</b></div>
            <div style="font-size: 18px; text-transform: uppercase;"><b>{{ $orgName }}</b></div>
            <div style="font-size: 16px;"><b>{{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="3" height="5"></td></tr>
</table>

<ol style="list-style: upper-alpha;">
    <li>
        <div style="text-transform: uppercase;">Gambaran Umum</div>
        <div style="text-align: justify">
            {{ $orgName }} adalah Badan Usaha yang didirikan dari transformasi UPK PNPM-MPd dengan
            kegiatan usaha Dana Bergulir Masyarakat (DBM) melalui produk usahanya SPP dan UEP. Dalam
            perkembangannya sebagian dari laba DBM UPK PNPM-MPd sesuai PP 11 tahun 2021 dapat digunakan untuk
            membentuk unit usaha sebagaimana yang disebut dalam aktifitas investasi.
        </div>

        <p style="text-align: justify">
            Bumdesma Lkd setelah didirikan sesuai ketentuan PP 11 tahun 2021 dilaksanakan transformasi
            sesuai Permendesa PDTT Nomor 15 tahun 2021 yang meliputi pengalihan aset, pengalihan kelembagaan,
            pengalihan personil, dan pengalihan kegiatan usaha. Modal awal Pendirian Bumdesma Lkd sesuai dengan
            ketentuan tersebut adalah berasal dari keseluruhan pengalihan keseluruhan aset DBM Eks PNPM MPd
            (Permendesa PDTT 15 tahun 2021 Pasal 5) yang dicatat sebagai Ekuitas Bumdesma Lkd ditambah dengan
            Penyertaan Modal Desa. Yang kemudian didalam laporan posisi keuangan ekuitas yang berasal dari Aset
            DBM Eks PNPM Mpd disebut Modal Masyarakat Desa (Permendesa PDTT 15 tahun 2021 Pasal 6).
        </p>
        <p style="text-align: justify">
            Sesuai dengan ketentuan UU Cipta Kerja No 11 Tahun 2020 bahwa Menetapkan status Badan hukum BUM
            Desa pada ketentuan Pasal 117 "bahwa Badan Usaha Milik Desa yang selanjutnya disebut BUM Desa adalah
            Badan hukum yang didirikan oleh desa dan atau bersama desa-desa guna mengelola usaha, memanfaatkan
            aset, mengembangkan investasi dan produktivitas, menyediakan jasa pelayanan, dan atau jenis usaha
            lainnya untuk sebesar-besarnya kesejahteraan masyarakat desa." Status inilah yang menjadi dasar
            hukum pelaksanaan usaha didirikan dengan kegiatan Usaha Utama DBM.
        </p>
        <p style="text-align: justify">
            {{ $orgName }} didirikan di {{ $district }} berdasarkan PERATURAN BERSAMA KEPALA DESA
            NOMOR {{ $identity['registration_number'] ?? '' }} dan mendapatkan Sertifikat Badan Hukum dari
            Menteri Hukum dan Hak Asasi Manusia. {{ $orgName }} menjalankan usaha pinjaman Dana Bergulir
            Masyarakat yang masuk dalam kategori usaha mikrofinance dan berdomisili di {{ $district }}
            dengan perangkat organisasi sebagai berikut:
        </p>
        <table style="margin-top: -10px; margin-left: 15px;">
            @foreach (($personalia ?? []) as $p)
                <tr>
                    <td style="padding: 0px; 4px;">{{ $p['sebutan'] }}</td>
                    <td style="padding: 0px; 4px;">:</td>
                    <td style="padding: 0px; 4px;">{{ $p['nama'] }}</td>
                </tr>
            @endforeach
        </table>
    </li>
    <li style="margin-top: 12px;">
        <div style="text-transform: uppercase;">Ikhtisar Kebijakan Akutansi</div>
        <ol>
            @foreach ($policies as $policy)
                <li>
                    {{ $policy['title'] }}
                    <ol style="list-style: lower-alpha;">
                        @foreach ($policy['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ol>
                </li>
            @endforeach
        </ol>
    </li>
    <li style="margin-top: 12px;">
        <div style="text-transform: uppercase;">Informasi Tambahan Laporan Keuangan</div>
        <div>
            <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
                <tr><td colspan="3" height="5"></td></tr>
                <tr style="background: #000; color: #fff;">
                    <td width="30">Kode</td>
                    <td width="300">Nama Akun</td>
                    <td align="right">Saldo</td>
                </tr>
                <tr><td colspan="3" height="2"></td></tr>

                @foreach (($summary['sections'] ?? []) as $lev1)
                    <tr style="background: rgb(74, 74, 74); color: #fff;">
                        <td height="20" colspan="3" align="center"><b>{{ $lev1['code'] }}. {{ $lev1['name'] }}</b></td>
                    </tr>
                    @foreach ($lev1['children'] as $lev2)
                        <tr style="background: rgb(167, 167, 167); font-weight: bold;">
                            <td>{{ $lev2['code'] }}.</td>
                            <td colspan="2">{{ $lev2['name'] }}</td>
                        </tr>
                        @foreach ($lev2['children'] as $lev3)
                            @php $sumSaldo = 0; @endphp
                            @foreach ($lev3['children'] as $lev4)
                                @php $sumSaldo += $lev4['balance']; @endphp
                            @endforeach
                            <tr style="background: rgb(200, 200, 200);">
                                <td>{{ $lev3['code'] }}.</td>
                                <td>{{ $lev3['name'] }}</td>
                                <td align="right">
                                    @if ($sumSaldo < 0)
                                        ({{ number_format(abs($sumSaldo), 2) }})
                                    @else
                                        {{ number_format($sumSaldo, 2) }}
                                    @endif
                                </td>
                            </tr>
                            @foreach ($lev3['children'] as $lev4)
                                <tr style="background: rgb(255, 255, 255);">
                                    <td>{{ $lev4['code'] }}.</td>
                                    <td>{{ $lev4['name'] }}</td>
                                    <td align="right">
                                        @if ($lev4['balance'] < 0)
                                            ({{ number_format(abs($lev4['balance']), 2) }})
                                        @else
                                            {{ number_format($lev4['balance'], 2) }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    @endforeach
                    <tr style="background: rgb(167, 167, 167); font-weight: bold;">
                        <td height="20" colspan="2" align="left"><b>Jumlah {{ $lev1['name'] }}</b></td>
                        <td align="right">{{ number_format($lev1['balance'], 2) }}</td>
                    </tr>
                    <tr><td colspan="3" height="2"></td></tr>
                @endforeach
                <tr style="background: rgb(167, 167, 167); font-weight: bold;">
                    <td height="20" colspan="2" align="left"><b>Jumlah Liabilitas + Ekuitas </b></td>
                    <td align="right">{{ number_format($summary['total_liability_equity'] ?? 0, 2) }}</td>
                </tr>
            </table>
        </div>

        @if (round($summary['difference'] ?? 0, 2) != 0)
            <div style="color: #f44335">
                Ada selisih antara Jumlah Aset dan Jumlah Liabilitas + Ekuitas sebesar
                <b>Rp. {{ number_format($summary['difference'] ?? 0, 2) }}</b>
            </div>
        @endif
    </li>
    <li style="margin-top: 12px;">
        <div style="text-transform: uppercase;">Pembagian Laba Usaha</div>
        <ol>
            <li>
                Pembagian atas laba usaha dibagi menjadi Laba dibagikan dan Laba ditahan.
            </li>
            <li>
                Mendasar kepada Permendesa PDTT Nomor 15 Tahun 2021 maka ketentuan pembagian laba Bumdesma Lkd adalah
                sebagai berikut:
                <ol style="list-style: lower-latin;">
                    <li>
                        Hasil usaha yang dibagikan paling sedikit terdiri atas: bagian milik bersama masyarakat Desa;
                        dan bagian Desa.
                    </li>
                    <li>
                        Besaran masing-masing bagian dihitung berdasarkan persentase penyertaan modal dan dituangkan
                        dalamanggaran dasar.
                    </li>
                </ol>
            </li>
            <li>
                Adapun nilai/alokasi pembagiannya adalah sebagai berukut:
                <ol style="list-style: lower-latin;">
                    <li>
                        <div>Alokasi laba Bagian Desa;</div>
                        <table cellspacing="0" cellpadding="0">
                            <tr>
                                <td class="b" colspan="3" align="center">Desa</td>
                                <td class="b" align="center">s/d Tahun {{ $year - 2 }}</td>
                                <td class="b" align="center">Tahun {{ $year - 1 }}</td>
                                <td class="b" align="center">s/d Tahun {{ $year }}</td>
                            </tr>
                            @foreach (($profit_distribution['villages'] ?? []) as $i => $v)
                                <tr>
                                    <td>{{ $i + 1 }}.</td>
                                    <td>{{ $v['name'] ?? '' }}</td>
                                    <td>:</td>
                                    <td width="70" align="right">{{ number_format($v['prior'] ?? 0, 2) }}</td>
                                    <td width="70" align="right">{{ number_format($v['current'] ?? 0, 2) }}</td>
                                    <td width="70" align="right">{{ number_format(($v['prior'] ?? 0) + ($v['current'] ?? 0), 2) }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </li>
                    <li>
                        Bagian milik bersama masyarakat Desa digunakan untuk kegiatan sosial kemasyarakatan dan
                        pengembangan kapasitas kelompok pemanfaat.
                    </li>
                </ol>
            </li>
        </ol>
    </li>

    @if (! empty($notes))
        <li style="margin-top: 12px;">
            <div style="text-transform: uppercase;">Lain Lain</div>
            <div style="text-align: justify">{{ $notes }}.</div>
        </li>
    @endif

    <li style="margin-top: 12px;">
        <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
            <tr>
                <td align="justify">
                    <div style="text-transform: uppercase;">Penutup</div>
                    <div style="text-align: justify">
                        Laporan Keuangan {{ $orgName }} ini disajikan dengan berpedoman pada Keputusan
                        Kementerian Desa Nomor 136/2022 Tentang Panduan Penyusunan Pelaporan Bumdes. Dimana yang
                        dimaksud Bumdes yang dimaksud dalam Keputusan Kementerian Desa adalah meliputi Bumdes,
                        Bumdesma dan Bumdesma Lkd. Catatan atas Laporan Keuangan (CaLK) ini merupakan bagian tidak
                        terpisahkan dari Laporan Keuangan Badan Usaha Milik Desa Bersama {{ $orgName }} untuk
                        Laporan Operasi {{ $period['period_label'] ?? '' }}. Selanjutnya Catatan atas Laporan
                        Keuangan ini diharapkan untuk dapat berguna bagi pihak-pihak yang berkepentingan
                        (stakeholders) serta memenuhi prinsip-prinsip transparansi, akuntabilitas,
                        pertanggungjawaban, independensi, dan fairness dalam pengelolaan keuangan {{ $orgName }}.
                    </div>

                    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;" class="p">
                        <tr>
                            <td>
                                <div style="margin-top: 16px;"></div>
                                {!! $tanda_tangan ?? '' !!}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </li>
</ol>
@endsection

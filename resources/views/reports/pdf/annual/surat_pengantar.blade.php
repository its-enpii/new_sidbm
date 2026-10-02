@extends('reports.pdf.layout')

@section('content')
    @php
        $orgName = $identity['legal_name'] ?? $identity['short_name'] ?? config('app.name');
        $district = $identity['district_name'] ?? '';
        $regencyName = $identity['regency_name'] ?? '';
        $regencyAddress = $identity['regency_address'] ?? '';
        $regencyCode = $identity['regency_code'] ?? '';
        $directorTitle = $identity['director_title'] ?? 'Direktur Utama';
    @endphp
    <table border="0">
        <tr>
            <td width="5%">Nomor</td>
            <td width="50%">: ______________________</td>
            <td width="45%" align="right">{{ $district }}, {{ $date_formatted }}</td>
        </tr>
        <tr>
            <td>Lampiran</td>
            <td>: 1 Bendel</td>
        </tr>
        <tr>
            <td>Perihal</td>
            <td>: Laporan Keuangan</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td style="padding-left: 8px;">
                &nbsp; <u>Sampai Dengan {{ $period_label }}</u>
            </td>
        </tr>
        <tr>
            <td colspan="3" height="15"></td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td colspan="2" align="left" style="padding-left: 8px;">
                <div><b>Kepada Yth.</b></div>
                <div><b>{{ $regencyName }}</b></div>
                <div><b>Di {{ $regencyAddress }}.</b></div>
            </td>
        </tr>
        <tr>
            <td colspan="3" height="15"></td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td colspan="2" style="padding-left: 8px; text-align: justify;">
                <div>Dengan Hormat,</div>
                <div>
                    Bersama ini kami sampaikan Laporan Keuangan {{ $orgName }} {{ $district }} sampai dengan
                    {{ $period_label }} sebagai berikut:
                    <ol>
                        <li>Laporan Neraca</li>
                        <li>Laporan Laba Rugi</li>
                        <li>Laporan Arus kas</li>
                        <li>Laporan Perubahan Ekuitas</li>
                        <li>Catatan Atas Laporan Keuangan (CALK)</li>
                    </ol>
                </div>
                <div>
                    Demikian laporan kami sampaikan, atas perhatiannya kami ucapkan terima kasih.
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="3" height="15"></td>
        </tr>
        <tr>
            <td colspan="2"></td>
            <td align="center">
                <div>{{ $orgName }} {{ $district }}</div>
                <div>{{ $directorTitle }},</div>
                <br>
                <br>
                <br>
                <br>
                @if (! empty($tanda_tangan))
                    {!! $tanda_tangan !!}
                @else
                    <div><b>{{ $identity['director_name'] ?? '' }}</b></div>
                @endif
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <div>
                    Tembusan : {{ $regencyCode === '33.01' ? 'Kepada' : '' }}
                    <ol>
                        @if ($regencyCode === '33.01')
                            <li>Yth. Camat {{ $district }} (Sebagai Laporan)</li>
                            <li>Yth. Penasehat (Sebagai Laporan)</li>
                            <li>Yth. Pengurus Forum Bumdesma Kab. {{ $regencyName }}</li>
                            <li>Arsip</li>
                        @else
                            <li>Arsip</li>
                        @endif
                    </ol>
                </div>
            </td>
        </tr>
    </table>
@endsection

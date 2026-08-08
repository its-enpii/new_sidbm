@php
    $documentTitle = $document['label'] ?? '';
@endphp

<style>
    * {
        font-family: Arial, Helvetica, sans-serif;
    }

    html {
        margin: 75.59px;
        margin-left: 94.48px;
    }

    ul,
    ol {
        margin-left: -10px;
        page-break-inside: auto !important;
    }

    header {
        position: fixed;
        top: -10px;
        left: 0px;
        right: 0px;
    }

    table tr th,
    table tr td {
        padding: 2px 4px;
    }

    table.p0 tr th,
    table.p0 tr td {
        padding: 0px !important;
    }

    .break {
        page-break-after: always;
    }

    li {
        text-align: justify;
    }

    .l {
        border-left: 1px solid #000;
    }

    .t {
        border-top: 1px solid #000;
    }

    .r {
        border-right: 1px solid #000;
    }

    .b {
        border-bottom: 1px solid #000;
    }
</style>

<title>{{ $documentTitle }}</title>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="3" align="center">
            <div style="font-size: 18px; text-decoration: underline">
                <b>FC KTP PEMANFAAT DAN PENJAMIN</b>
            </div>
            <div style="font-size: 16px;">
                <b>KELOMPOK {{ strtoupper($group['name']) }}</b>
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="5"></td>
    </tr>
</table>

<table border="1" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr style="background: rgb(232, 232, 232)">
        <th class="l t b" width="5%" align="center">No</th>
        <th class="l t b" width="30%" align="center">Nama Pemanfaat</th>
        <th class="l t b" width="20%" align="center">NIK Pemanfaat</th>
        <th class="l t b" width="30%" align="center">Nama Penjamin</th>
        <th class="l t b r" width="15%" align="center">Status FC</th>
    </tr>
    @foreach ($beneficiaries as $b)
        <tr>
            <td class="l b" align="center">{{ $loop->iteration }}</td>
            <td class="l b">{{ $b['name'] }}</td>
            <td class="l b" align="center">{{ $b['nik'] }}</td>
            <td class="l b">{{ $b['guarantor'] }}</td>
            <td class="l b r" align="center">&nbsp;</td>
        </tr>
    @endforeach
</table>
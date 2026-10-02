@php
    $type = $type ?? 'pdf';

    if ($type == 'excel') {
        $nama_file = ucwords(str_replace('_', ' ', $title ?? 'laporan'));

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $nama_file . '.xls"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');
    }

    $legalName = strtoupper($identity['legal_name'] ?? $identity['short_name'] ?? config('app.name'));
    $district = $identity['district_name'] ?? '';
    $regency = $identity['regency_name'] ?? '';
    $locationLine = trim(($district !== '' ? 'Kec. ' . $district : '') . ' ' . ($regency !== '' ? 'Kab. ' . $regency : ''));
    $registration = $identity['registration_number'] ?? '';
    $address = $identity['address'] ?? '';
    $phone = $identity['phone'] ?? '';
    $email = $identity['email'] ?? '';
    $logoUrl = $identity['logo_url'] ?? null;

    $infoLine = $address;
    if ($address !== '' && $phone !== '') {
        $infoLine .= ', Telp. ' . $phone;
    } elseif ($phone !== '') {
        $infoLine = 'Telp. ' . $phone;
    }
@endphp
<!DOCTYPE html>
<html lang="id" translate="no">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $title ?? 'Laporan' }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 75.59px; margin-left: 94.48px; }
        ul, ol { margin-left: -10px; page-break-inside: auto !important; }
        header {
            position: fixed;
            top: -10px;
            left: 0px;
            right: 0px;
        }
        table tr th,
        table tr td,
        table tr td table.p tr td { padding: 2px 4px !important; }
        table tr td table tr td { padding: 0 !important; }
        table.p0 tr th,
        table.p0 tr td { padding: 0px !important; }
        .break { page-break-after: always; }
        li { text-align: justify; }
        .l { border-left: 1px solid #000; }
        .t { border-top: 1px solid #000; }
        .r { border-right: 1px solid #000; }
        .b { border-bottom: 1px solid #000; }
    </style>
</head>
<body>
    <header>
        <table width="100%" style="border-bottom: 1px solid grey;">
            <tr>
                <td width="30">
                    @if (! empty($logoUrl))
                        <img src="{{ $logoUrl }}" width="40" alt="Logo">
                    @else
                        &nbsp;
                    @endif
                </td>
                <td>
                    <div style="font-size: 12px;">{{ $legalName }}</div>
                    <div style="font-size: 12px;">
                        <b>{!! strtoupper($locationLine) !!}</b>
                    </div>
                </td>
            </tr>
        </table>
        <table width="100%" style="position: relative; top: -10px;">
            <tr>
                <td>
                    <span style="font-size: 8px; color: grey;">
                        <i>{{ $registration }}</i>
                    </span>
                </td>
                <td align="right">
                    <span style="font-size: 8px; color: grey;">
                        <i>{{ $infoLine }}</i>
                    </span>
                </td>
            </tr>
        </table>
    </header>

    <main style="position: relative; top: 60px; font-size: 12px; padding-bottom: 37.79px;">
        @yield('content')
    </main>
</body>
</html>

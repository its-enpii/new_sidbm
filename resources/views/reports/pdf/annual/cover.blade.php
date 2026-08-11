<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>COVER BUKU LAPORAN TAHUNAN</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        html { margin: 50px; }
        body {
            width: 100%;
            height: 96%;
            border: 3px double #1a365d;
            padding: 20px;
            text-align: center;
            position: relative;
        }
        .header-title { font-size: 22px; font-weight: bold; color: #1a365d; margin-top: 40px; }
        .sub-title { font-size: 16px; font-weight: bold; color: #4a5568; margin-top: 8px; }
        .logo-box { margin-top: 100px; margin-bottom: 100px; }
        .footer-box {
            position: absolute;
            bottom: 40px;
            left: 20px;
            right: 20px;
            border-top: 1px solid #cbd5e0;
            padding-top: 15px;
        }
    </style>
</head>
<body>
    <div class="header-title">LAPORAN KEUANGAN & PERTANGGUNGJAWABAN TAHUNAN</div>
    <div class="sub-title">{{ strtoupper($period_label) }}</div>

    <div class="logo-box">
        @if (! empty($identity['logo_url']))
            <img src="{{ $identity['logo_url'] }}" width="180" alt="Logo">
        @else
            <div style="font-size: 40px; font-weight: bold; color: #2b6cb0;">{{ $identity['short_name'] ?? 'BUMDESMA' }}</div>
        @endif
    </div>

    <div class="footer-box">
        <div style="font-size: 16px; font-weight: bold; color: #1a365d;">{{ strtoupper($identity['legal_name']) }}</div>
        <div style="font-size: 12px; font-weight: bold; color: #4a5568;">KECAMATAN {{ strtoupper($identity['district_name']) }} KABUPATEN {{ strtoupper($identity['regency_name']) }}</div>
        <div style="font-size: 10px; color: #718096; margin-top: 4px;">{{ $identity['address'] }} {{ $identity['phone'] ? '• Telp: ' . $identity['phone'] : '' }}</div>
        <div style="font-size: 10px; color: #718096;">SK Kemenkumham RI: {{ $identity['registration_number'] ?: '—' }}</div>
        <div style="font-size: 11px; font-weight: bold; color: #2b6cb0; margin-top: 10px;">TAHUN BUKU {{ $year }}</div>
    </div>
</body>
</html>
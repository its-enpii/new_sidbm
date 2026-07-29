<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Laporan' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 2px; text-align: center; }
        h2 { font-size: 13px; margin: 0 0 12px; text-align: center; font-weight: normal; }
        .meta { text-align: center; margin-bottom: 12px; color: #444; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 3px 5px; vertical-align: top; }
        th { background: #e8eef4; border-bottom: 1px solid #999; font-size: 10px; text-transform: uppercase; }
        td { border-bottom: 1px solid #eee; }
        .num { text-align: right; white-space: nowrap; }
        .ctr { text-align: center; }
        .bold { font-weight: bold; }
        .section { background: #d0d7de; font-weight: bold; }
        .sub { background: #eef2f5; font-weight: bold; }
        .total { background: #f5f5f5; font-weight: bold; border-top: 1px solid #333; }
        .neg { color: #a00; }
        .muted { color: #666; font-size: 10px; }
    </style>
</head>
<body>
    <div class="meta">
        <div class="bold">{{ $identity['legal_name'] ?? $identity['short_name'] ?? config('app.name') }}</div>
    </div>
    <h1>{{ $title }}</h1>
    <h2>{{ $period['period_label'] ?? '' }}</h2>
    @yield('content')
</body>
</html>

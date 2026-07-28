<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="classic">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'SIDBM Next') }}</title>
    <script>
        (function () {
            try {
                var t = localStorage.getItem('sidbm-theme');
                var ok = { classic:1, forest:1, amber:1, violet:1, ocean:1, rose:1, midnight:1 };
                if (t && ok[t]) document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
    @vite('resources/js/app.js')
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>


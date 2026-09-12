<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <meta name="description" content="@yield('description')">
    <link rel="canonical" href="@yield('canonical')">
    <meta property="og:title" content="@yield('title')">
    <meta property="og:description" content="@yield('description')">
    <meta property="og:url" content="@yield('canonical')">
    <meta name="twitter:card" content="@yield('twitter_card', 'summary')">
    <meta name="robots" content="@yield('robots', 'index, follow')">
    @yield('json_ld')
</head>
<body>
    <main>
        @yield('content')
    </main>
</body>
</html>

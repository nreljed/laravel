<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" suppressHydrationWarning>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Favicon -->
        @php
            $favicon = \Nreljed\DashboardCore\Services\SettingsService::get('app_favicon', '');
            $faviconUrl = $favicon ?: asset('favicon.svg');
            $ext = pathinfo(parse_url($faviconUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
            $mime = match($ext) {
                'svg' => 'image/svg+xml',
                'png' => 'image/png',
                default => 'image/x-icon',
            };
        @endphp
        <link rel="icon" type="{{ $mime }}" href="{{ $faviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $favicon && $ext !== 'svg' ? $favicon : \Nreljed\DashboardCore\Services\SettingsService::get('app_logo', '') }}">

        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600&display=swap" rel="stylesheet" />

        <!-- Prevent flash of wrong theme -->
        <script nonce="{{ Vite::cspNonce() }}">
            (function () {
                try {
                    var theme = localStorage.getItem('theme') || 'system';
                    var dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    if (dark) document.documentElement.classList.add('dark');
                    else document.documentElement.classList.remove('dark');
                } catch (e) {}
            })();
        </script>

        <!-- Scripts -->
        @routes(null, Vite::cspNonce())
        @viteReactRefresh
        @vite(['resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>

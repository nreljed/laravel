<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>@yield('title') — {{ config('app.name', 'Cost Breaker 360') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600&display=swap" rel="stylesheet" />
        @vite('resources/js/app.tsx')
    </head>
    <body class="font-sans antialiased bg-background text-foreground">
        <div class="relative flex items-top justify-center min-h-screen sm:items-center sm:pt-0">
            <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
                <div class="flex flex-col items-center pt-8 sm:pt-0 text-center">
                    <div class="flex items-center justify-center w-20 h-20 rounded-full bg-muted mb-6">
                        <svg class="w-10 h-10 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                    </div>

                    <div class="flex items-center gap-4">
                        <span class="text-5xl font-bold text-foreground">@yield('code')</span>
                        <span class="text-5xl font-bold text-muted-foreground/30">|</span>
                        <span class="text-xl text-muted-foreground">@yield('message')</span>
                    </div>

                    <p class="mt-4 text-sm text-muted-foreground">
                        @yield('description')
                    </p>

                    <div class="mt-8 flex items-center gap-4">
                        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : '/' }}"
                           class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground hover:bg-primary/90 h-10 px-4 py-2">
                            &larr; Go Back
                        </a>
                        <a href="{{ url('/') }}"
                           class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-10 px-4 py-2">
                            Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>

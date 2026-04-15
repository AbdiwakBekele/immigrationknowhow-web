<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title data-inertia>{{ config('app.name', 'ImmigrationKnowHow') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|outfit:400,500,600,700,800|jetbrains-mono:400,500" rel="stylesheet" />

        <!-- Favicon -->
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">

        <!-- Scripts -->
        @routes
        @if(app()->environment('local'))
            <script type="module" src="http://127.0.0.1:5173/@@vite/client"></script>
            <script type="module" src="http://127.0.0.1:5173/resources/js/app.js"></script>
        @else
            @vite(['resources/js/app.js'])
        @endif
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900">
        @inertia
    </body>
</html>

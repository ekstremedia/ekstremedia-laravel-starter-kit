<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ config('app.name', 'Laravel Starter Kit') }}</title>

    <script>
        (function() {
            var key = '{{ env('VITE_APP_STORAGE_KEY', 'starter_kit_settings') }}';
            try {
                var s = JSON.parse(localStorage.getItem(key) || '{}');
                if (s.dark_mode !== false) document.documentElement.classList.add('dark');
            } catch(e) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @if (! app()->runningUnitTests())
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @endif
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>

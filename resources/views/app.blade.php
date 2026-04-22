<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ config('app.name', 'Laravel Starter Kit') }}</title>

    <script>
        (function() {
            var key = @json(config('app.storage_key'));
            var root = document.documentElement;
            try {
                var s = JSON.parse(localStorage.getItem(key) || '{}');
                // Legacy dark-mode flag still controls PrimeVue's dark palette.
                if (s.dark_mode !== false) root.classList.add('dark');
                // Command tokens are driven by data-* attributes — set pre-hydrate
                // so first paint lands on the right theme.
                var theme = s.theme === 'light' || s.theme === 'hc' ? s.theme : 'dark';
                root.setAttribute('data-theme', theme);
                if (theme !== 'light') root.classList.add('dark'); // hc is also dark-ish for PrimeVue
                root.setAttribute('data-accent', ['emerald', 'amber', 'violet'].indexOf(s.accent) >= 0 ? s.accent : 'cobalt');
                root.setAttribute('data-density', ['compact', 'relaxed'].indexOf(s.density) >= 0 ? s.density : 'comfortable');
                root.style.setProperty('--rail-w', s.rail_expanded === true ? '180px' : '52px');
            } catch(e) {
                root.classList.add('dark');
                root.setAttribute('data-theme', 'dark');
                root.setAttribute('data-accent', 'cobalt');
                root.setAttribute('data-density', 'comfortable');
                root.style.setProperty('--rail-w', '52px');
            }
        })();
    </script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|jetbrains-mono:400,500,600|instrument-sans:400,500,600,700" rel="stylesheet" />

    @if (! app()->runningUnitTests())
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @endif
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>

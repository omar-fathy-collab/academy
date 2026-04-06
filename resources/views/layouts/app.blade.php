<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Shefae') }} - @yield('title', 'Academy System')</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- AOS (Animate On Scroll) -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <!-- Bootstrap & FontAwesome CDN (Fallbacks in case local fails) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Fawry Pay (Staging) -->
    <script src="https://atfawry.fawrystaging.com/atfawry/plugin/assets/payments/js/fawrypay-payments.js"></script>

    <!-- Plyr (Video Player) -->
    <link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css" />
    <script src="https://cdn.plyr.io/3.7.8/plyr.polyfilled.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <!-- Scripts & Styles -->
    @routes
    @vite(['resources/js/app.js'])
    
    {{-- Prevent Flash of Unstyled Content (FOUC) --}}
    <script>
        (function() {
            const theme = localStorage.getItem('app-theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', theme);
            const isSidebarOpen = localStorage.getItem('sidebar-open') !== 'false';
            document.documentElement.classList.add(isSidebarOpen ? 'sidebar-expanded' : 'sidebar-collapsed');
        })();
    </script>

    <style>
        [x-cloak] { display: none !important; }
        {{-- Suppress transitions on initial load to prevent "sliding" effects --}}
        .no-transition, .no-transition * { transition: none !important; }
    </style>
    
    @stack('styles')
</head>

<body class="font-sans antialiased" x-data="{ 
    theme: localStorage.getItem('app-theme') || 'light',
    init() {
        document.documentElement.setAttribute('data-bs-theme', this.theme);
        AOS.init({ duration: 800, once: true });
    },
    toggleTheme() {
        this.theme = this.theme === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-bs-theme', this.theme);
        localStorage.setItem('app-theme', this.theme);
    }
}">
    @yield('body')

    @include('partials.flash-notifications')
    @stack('scripts')
</body>

</html>

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | Royal Travel Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('styles')
</head>
<body class="rt-admin-shell">
    <div class="rt-shell">
        @include('admin.components.sidebar')
        <div class="rt-main-column">
            @include('admin.components.navbar')
            <main class="rt-page" id="main-content">
                @yield('content')
            </main>
            <footer class="rt-footer"><span>© {{ date('Y') }} Royal Travel Platform</span><span>Admin Console</span></footer>
        </div>
    </div>
    @stack('scripts')
</body>
</html>

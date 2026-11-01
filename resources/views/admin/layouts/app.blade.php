
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}"
      dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        @yield('title', 'Dashboard') | Royal Travel Admin
    </title>

    {{-- Global CSS and JavaScript --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Page-specific CSS --}}
    @yield('styles')

</head>

<body class="min-h-screen bg-[#F7F8FA] text-slate-800 antialiased">

    <div class="flex min-h-screen">

        {{-- Admin Sidebar --}}
        <div class="hidden shrink-0 lg:block">
            @include('admin.components.sidebar')
        </div>

        {{-- Main Area --}}
        <div class="flex min-w-0 flex-1 flex-col">

            {{-- Admin Navbar --}}
            @include('admin.components.navbar')

            {{-- Page Content --}}
            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">

                <div class="mx-auto max-w-[1600px]">
                    @yield('content')
                </div>

            </main>

            {{-- Footer --}}
            <footer class="border-t border-slate-200 bg-white px-6 py-4">
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-400">
                    <span>© {{ date('Y') }} Royal Travel Platform</span>
                    <span>Admin Console</span>
                </div>
            </footer>

        </div>

    </div>

    @stack('scripts')

</body>
</html>

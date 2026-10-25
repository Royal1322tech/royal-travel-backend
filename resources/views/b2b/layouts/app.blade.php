<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Dashboard') | Royal Vacation</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F7F8FC] font-sans text-[#02143C] antialiased">

    <div class="flex min-h-screen">

        {{-- Sidebar --}}
        @include('b2b.components.sidebar')

        {{-- Main Content --}}
        <div class="flex min-w-0 flex-1 flex-col">

            {{-- Navbar --}}
            @include('b2b.components.navbar')

            <main class="flex-1 p-5 lg:p-8">
                @yield('content')
            </main>

        </div>
    </div>

</body>
</html>

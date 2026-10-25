
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Admin Login | Royal Travel</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-[#F8F9FC] font-sans antialiased">

<div class="min-h-screen lg:grid lg:grid-cols-2">

    {{-- LEFT: BRAND PANEL --}}
    <section class="relative hidden min-h-screen overflow-hidden lg:flex lg:flex-col lg:justify-between">

        <img
            src="{{ asset('images/admin-login-bg.jpg') }}"
            alt="Luxury travel destination"
            class="absolute inset-0 h-full w-full object-cover"
        >

        <div class="absolute inset-0 bg-gradient-to-r from-white/95 via-white/70 to-white/10"></div>

        <div class="relative z-10 flex h-full flex-col justify-between px-12 py-12 xl:px-20">

            <div>
                <div class="inline-flex flex-col items-center">
                    <div class="text-3xl font-bold tracking-[0.12em] text-[#02143C]">
                        ROYAL
                    </div>
                    <div class="mt-1 text-xs tracking-[0.55em] text-[#B7731E]">
                        TRAVEL
                    </div>
                </div>
            </div>

            <div class="max-w-lg">

                <div class="mb-7 h-1 w-16 rounded-full bg-[#B7731E]"></div>

                <h1 class="text-4xl font-bold leading-tight tracking-tight text-[#02143C] xl:text-5xl">
                    Manage Your
                    <span class="block">Travel Business</span>
                </h1>

                <p class="mt-5 max-w-md text-lg leading-relaxed text-slate-600">
                    Hotels. Transfers. Flights. Visas. And More.
                    All in one platform.
                </p>

                <div class="mt-10 space-y-6">

                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/80 text-[#B7731E] shadow-sm">
                            <span class="text-xl">▥</span>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-[#02143C]">
                                Real-Time Insights
                            </h3>
                            <p class="mt-1 text-sm text-slate-600">
                                Track bookings, sales and performance.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/80 text-[#B7731E] shadow-sm">
                            <span class="text-xl">♙</span>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-[#02143C]">
                                Full Control
                            </h3>
                            <p class="mt-1 text-sm text-slate-600">
                                Manage users, suppliers and content.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/80 text-[#B7731E] shadow-sm">
                            <span class="text-xl">◇</span>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-[#02143C]">
                                Secure & Reliable
                            </h3>
                            <p class="mt-1 text-sm text-slate-600">
                                Protected access to your admin workspace.
                            </p>
                        </div>
                    </div>

                </div>
            </div>

            <p class="text-xs text-[#02143C]/60">
                © {{ date('Y') }} Royal Travel Platform
            </p>

        </div>
    </section>

    {{-- RIGHT: LOGIN PANEL --}}
    <section class="flex min-h-screen flex-col bg-[#F8F9FC] px-5 py-8 sm:px-10">

        <div class="flex justify-end">
            <span class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-[#02143C]">
                Admin Console
            </span>
        </div>

        <div class="flex flex-1 items-center justify-center py-10">

            <div class="w-full max-w-[520px] rounded-[28px] border border-slate-100 bg-white px-6 py-10 shadow-[0_20px_70px_rgba(2,20,60,0.06)] sm:px-12 sm:py-12">

                {{-- Logo --}}
                <div class="mb-10 text-center">

                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-[#02143C] text-2xl font-bold text-white">
                        R
                    </div>

                    <div class="text-2xl font-bold tracking-[0.12em] text-[#02143C]">
                        ROYAL
                    </div>

                    <div class="mt-1 text-[10px] font-semibold tracking-[0.55em] text-[#B7731E]">
                        TRAVEL
                    </div>

                    <div class="mx-auto mt-6 h-0.5 w-12 bg-[#B7731E]"></div>

                    <h2 class="mt-7 text-3xl font-bold tracking-tight text-[#02143C]">
                        Welcome Back
                    </h2>

                    <p class="mt-2 text-sm text-slate-500">
                        Sign in to your admin account
                    </p>

                </div>

                {{-- Login Form --}}
                <form method="POST"
                      action="{{ route('admin.login.submit') }}"
                      class="space-y-5">

                    @csrf

                    <div>
                        <label for="email" class="mb-2 block text-sm font-semibold text-[#02143C]">
                            Email Address
                        </label>

                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="Enter your email"
                            autocomplete="username"
                            required
                            autofocus
                            class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-sm text-[#02143C] outline-none transition placeholder:text-slate-400 focus:border-[#B7731E] focus:ring-2 focus:ring-[#B7731E]/10"
                        >

                        @error('email')
                            <p class="mt-2 text-xs text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-semibold text-[#02143C]">
                            Password
                        </label>

                        <div class="relative">
                            <input
                                id="password"
                                type="password"
                                name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                                class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 pe-12 text-sm text-[#02143C] outline-none transition placeholder:text-slate-400 focus:border-[#B7731E] focus:ring-2 focus:ring-[#B7731E]/10"
                            >

                            <button
                                type="button"
                                id="togglePassword"
                                aria-label="Show password"
                                aria-pressed="false"
                                class="absolute inset-y-0 end-3 flex items-center px-2 text-xs font-medium text-slate-500 hover:text-[#02143C]"
                            >
                                Show
                            </button>
                        </div>

                        @error('password')
                            <p class="mt-2 text-xs text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        class="mt-3 flex w-full items-center justify-center gap-3 rounded-xl bg-[#02143C] px-5 py-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#102858] focus:outline-none focus:ring-2 focus:ring-[#B7731E]"
                    >
                        Sign In
                        <span aria-hidden="true">→</span>
                    </button>

                </form>

                <p class="mt-8 text-center text-xs text-slate-400">
                    Authorized administrators only
                </p>

            </div>
        </div>

        <p class="text-center text-xs text-slate-400 lg:hidden">
            © {{ date('Y') }} Royal Travel Platform
        </p>

    </section>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('togglePassword');
    const password = document.getElementById('password');

    toggle.addEventListener('click', function () {
        const isHidden = password.type === 'password';

        password.type = isHidden ? 'text' : 'password';
        toggle.textContent = isHidden ? 'Hide' : 'Show';
        toggle.setAttribute('aria-pressed', String(isHidden));
        toggle.setAttribute(
            'aria-label',
            isHidden ? 'Hide password' : 'Show password'
        );
    });
});
</script>

</body>
</html>

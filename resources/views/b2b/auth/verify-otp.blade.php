<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify OTP | Royal Travel</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-6">
    <main class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <div class="mb-8">
            <div class="text-2xl font-bold text-[#02143C]">ROYAL TRAVEL</div>
            <p class="mt-2 text-sm text-slate-500">B2B Partner Dashboard</p>
        </div>

        <h1 class="text-xl font-semibold text-slate-900">Verify your identity</h1>
        <p class="mt-2 mb-6 text-sm text-slate-500">
            Enter the 6-digit verification code sent to your email.
        </p>

        @if ($errors->any())
            <div role="alert" class="mb-5 rounded-lg bg-red-50 p-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('b2b.otp.verify') }}" class="space-y-5">
            @csrf

            <div>
                <label for="otp" class="mb-2 block text-sm font-medium">
                    Verification code
                </label>
                <input
                    id="otp"
                    type="text"
                    name="otp"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    autocomplete="one-time-code"
                    required
                    autofocus
                    class="w-full rounded-lg border border-slate-300 px-4 py-3 text-center text-2xl tracking-[0.4em]"
                    placeholder="000000"
                >
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-[#02143C] py-3 font-semibold text-white hover:opacity-90"
            >
                Verify & Sign In
            </button>
        </form>

        <a
            href="{{ route('b2b.login') }}"
            class="mt-6 block text-center text-sm font-medium text-[#B7731E] hover:underline"
        >
            Back to login
        </a>
    </main>
</body>
</html>

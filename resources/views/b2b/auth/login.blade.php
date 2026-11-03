<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>B2B Login | Royal Travel</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-6">
    <main class="w-full max-w-md rounded-2xl bg-white p-8 shadow-sm border border-slate-200">
        <div class="mb-8">
            <div class="text-2xl font-bold text-[#02143C]">ROYAL TRAVEL</div>
            <p class="mt-2 text-sm text-slate-500">B2B Partner Dashboard</p>
        </div>

        <h1 class="text-xl font-semibold text-slate-900">Sign in</h1>
        <p class="mt-2 mb-6 text-sm text-slate-500">
            Enter your credentials to receive a verification code.
        </p>

        @if ($errors->any())
            <div role="alert" class="mb-5 rounded-lg bg-red-50 p-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('b2b.login.submit') }}" class="space-y-5">
            @csrf

            <div>
                <label for="email" class="block mb-2 text-sm font-medium">Email</label>
                <input id="email" type="email" name="email"
                       value="{{ old('email') }}" required autocomplete="username"
                       class="w-full rounded-lg border border-slate-300 px-4 py-3">
            </div>

            <div>
                <label for="password" class="block mb-2 text-sm font-medium">Password</label>
                <input id="password" type="password" name="password"
                       required autocomplete="current-password"
                       class="w-full rounded-lg border border-slate-300 px-4 py-3">
            </div>

            <div>
                <label for="company_id" class="block mb-2 text-sm font-medium">Company ID</label>
                <input id="company_id" type="text" name="company_id"
                       value="{{ old('company_id') }}" required
                       placeholder="Company UUID"
                       class="w-full rounded-lg border border-slate-300 px-4 py-3">
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-[#02143C] py-3 font-semibold text-white hover:opacity-90">
                Continue
            </button>
        </form>
    </main>
</body>
</html>

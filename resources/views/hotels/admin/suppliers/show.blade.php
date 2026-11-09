
@extends('admin.layouts.app')

@section('title', 'Supplier Details')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">

    <div>
        <a href="{{ route('admin.suppliers.index') }}"
           class="text-sm text-slate-500">
            ← Back to Suppliers
        </a>

        <h1 class="mt-4 text-3xl font-bold text-[#02143C]">
            {{ $supplier->name }}
        </h1>

        <p class="mt-2 text-sm text-slate-500">
            Supplier Details & API Integration
        </p>
    </div>

    @if (session('connection_success'))
        <div class="rounded-xl bg-green-50 p-4 text-green-800">
            {{ session('connection_success') }}
        </div>
    @endif

    @if (session('connection_error'))
        <div class="rounded-xl bg-red-50 p-4 text-red-800">
            {{ session('connection_error') }}
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-3">

        <div class="rounded-xl border bg-white p-6">
            <p class="text-sm text-slate-500">Supplier Status</p>
            <p class="mt-2 font-bold">
                {{ ucfirst($supplier->status) }}
            </p>
        </div>

        <div class="rounded-xl border bg-white p-6">
            <p class="text-sm text-slate-500">Environment</p>
            <p class="mt-2 font-bold">
                {{ ucfirst($environment) }}
            </p>
        </div>

        <div class="rounded-xl border bg-white p-6">
            <p class="text-sm text-slate-500">API Credentials</p>
            <p class="mt-2 font-bold">
                {{ $configured ? 'Configured' : 'Not Configured' }}
            </p>
        </div>

    </div>

    <div class="rounded-xl border bg-white p-6">

        <h2 class="text-xl font-bold text-[#02143C]">
            RateHawk API Connection
        </h2>

        <p class="mt-2 text-sm text-slate-500">
            Test the Sandbox connection without creating a booking.
        </p>

        @if ($supplier->adapter_code === 'ratehawk'
             && $environment === 'sandbox'
             && $configured)

            <form method="POST"
                  action="{{ route('admin.suppliers.test-connection', $supplier->id) }}"
                  class="mt-6">
                @csrf

                <button type="submit"
                        class="rounded-xl bg-[#02143C] px-6 py-3 font-semibold text-white">
                    Test Sandbox Connection
                </button>
            </form>

        @else
            <p class="mt-4 text-sm text-amber-700">
                Sandbox testing is unavailable with the current configuration.
            </p>
        @endif

    </div>

</div>
@endsection

@extends('b2b.layouts.app')

@section('title', 'Bookings')

@section('content')
@php
    $stats = [
        ['label' => 'Total Bookings', 'value' => 250, 'icon' => '▣', 'color' => 'blue'],
        ['label' => 'Confirmed', 'value' => 145, 'icon' => '✓', 'color' => 'green'],
        ['label' => 'Cancelled', 'value' => 25, 'icon' => '✕', 'color' => 'red'],
        ['label' => 'Pending Payment', 'value' => 55, 'icon' => '$', 'color' => 'amber'],
        ['label' => 'Refunded', 'value' => 25, 'icon' => '↩', 'color' => 'purple'],
    ];

    $bookings = [
        ['id' => 'RB-1048', 'customer' => 'John Doe', 'service' => 'Transfer', 'date' => 'May 23, 2025', 'amount' => '$300', 'payment' => 'Pending', 'status' => 'Pending'],
        ['id' => 'RB-1049', 'customer' => 'Sarah Ahmed', 'service' => 'Hotel', 'date' => 'Jun 12, 2025', 'amount' => '$450', 'payment' => 'Paid', 'status' => 'Confirmed'],
        ['id' => 'RB-1050', 'customer' => 'Michael Lee', 'service' => 'Transfer', 'date' => 'Jul 05, 2025', 'amount' => '$180', 'payment' => 'Pending', 'status' => 'Pending'],
        ['id' => 'RB-1051', 'customer' => 'Emma Wilson', 'service' => 'Hotel', 'date' => 'Aug 18, 2025', 'amount' => '$520', 'payment' => 'Refunded', 'status' => 'Cancelled'],
        ['id' => 'RB-1052', 'customer' => 'Omar Hassan', 'service' => 'Hotel', 'date' => 'Sep 10, 2025', 'amount' => '$300', 'payment' => 'Paid', 'status' => 'Confirmed'],
        ['id' => 'RB-1053', 'customer' => 'David Brown', 'service' => 'Hotel', 'date' => 'Oct 15, 2025', 'amount' => '$390', 'payment' => 'Failed', 'status' => 'Requested'],
    ];

    $statusColors = [
        'Confirmed' => 'bg-emerald-50 text-emerald-700',
        'Pending' => 'bg-amber-50 text-amber-700',
        'Cancelled' => 'bg-rose-50 text-rose-700',
        'Requested' => 'bg-fuchsia-50 text-fuchsia-700',
    ];

    $paymentColors = [
        'Paid' => 'bg-indigo-50 text-indigo-700',
        'Pending' => 'bg-amber-50 text-amber-700',
        'Refunded' => 'bg-slate-100 text-slate-700',
        'Failed' => 'bg-red-50 text-red-700',
    ];
@endphp

<div class="mx-auto max-w-7xl space-y-7">

    {{-- Statistics --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        @foreach ($stats as $stat)
            <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xl text-[#B7731E]">
                    {{ $stat['icon'] }}
                </div>
                <div>
                    <p class="text-xs text-slate-500">{{ $stat['label'] }}</p>
                    <p class="mt-1 text-2xl font-bold text-slate-950">{{ $stat['value'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Search</span>
                <input id="booking-search" type="search" placeholder="Booking ID or customer"
                       class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-[#B7731E]">
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Service Type</span>
                <select class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
                    <option>All Services</option>
                    <option>Hotel</option>
                    <option>Transfer</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Booking Status</span>
                <select class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
                    <option>All Statuses</option>
                    <option>Confirmed</option>
                    <option>Pending</option>
                    <option>Cancelled</option>
                    <option>Requested</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Payment Status</span>
                <select class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
                    <option>All Payments</option>
                    <option>Paid</option>
                    <option>Pending</option>
                    <option>Refunded</option>
                    <option>Failed</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Date From</span>
                <input type="date" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Date To</span>
                <input type="date" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Customer Type</span>
                <select class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
                    <option>All Customers</option>
                    <option>B2B</option>
                    <option>B2C</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-700">Supplier</span>
                <select class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
                    <option>All Suppliers</option>
                    <option>RateHawk</option>
                    <option>Direct Supplier</option>
                </select>
            </label>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <button type="button" class="rounded-xl border border-[#B7731E] px-7 py-3 text-sm font-semibold text-[#B7731E]">
                Reset
            </button>
            <button type="button" class="rounded-xl bg-[#B7731E] px-7 py-3 text-sm font-semibold text-white">
                Apply Filter
            </button>
        </div>
    </section>

    {{-- Bookings Table --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
            <h2 class="text-xl font-bold text-[#02143C]">Bookings</h2>

            <div class="flex items-center gap-3">
                <select class="rounded-xl border border-slate-200 px-4 py-3 text-sm">
                    <option>Booking Date (Newest First)</option>
                    <option>Booking Date (Oldest First)</option>
                    <option>Amount (High to Low)</option>
                </select>

                <button type="button" class="rounded-xl border border-[#B7731E] px-5 py-3 text-sm font-semibold text-[#B7731E]">
                    Export
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[850px] text-left text-sm">
                <thead class="bg-slate-100 text-slate-700">
                    <tr>
                        @foreach (['Booking ID', 'Customer', 'Service', 'Date', 'Amount', 'Payment', 'Status', 'Action'] as $heading)
                            <th class="px-4 py-4 font-semibold">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @foreach ($bookings as $booking)
                        <tr class="booking-row hover:bg-slate-50"
                            data-booking-id="{{ strtolower($booking['id']) }}"
                            data-customer="{{ strtolower($booking['customer']) }}">
                            <td class="px-4 py-5 font-semibold text-[#02143C]">
                                {{ $booking['id'] }}
                            </td>

                            <td class="px-4 py-5">{{ $booking['customer'] }}</td>

                            <td class="px-4 py-5">
                                {{ $booking['service'] === 'Hotel' ? '🛏' : '🚘' }}
                                {{ $booking['service'] }}
                            </td>

                            <td class="px-4 py-5 text-slate-600">{{ $booking['date'] }}</td>

                            <td class="px-4 py-5 font-semibold">{{ $booking['amount'] }}</td>

                            <td class="px-4 py-5">
                                <span class="rounded-lg px-3 py-2 text-xs font-semibold {{ $paymentColors[$booking['payment']] }}">
                                    {{ $booking['payment'] }}
                                </span>
                            </td>

                            <td class="px-4 py-5">
                                <span class="rounded-lg px-3 py-2 text-xs font-semibold {{ $statusColors[$booking['status']] }}">
                                    {{ $booking['status'] }}
                                </span>
                            </td>

                            <td class="px-4 py-5">
                                <button type="button"
                                        onclick="alert('Booking actions — Demo preview only')"
                                        class="rounded-lg border border-slate-200 px-3 py-2 text-slate-600 hover:border-[#B7731E] hover:text-[#B7731E]">
                                    View
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- Pagination --}}
    <div class="flex items-center justify-between gap-4">
        <span class="text-sm text-slate-500">Showing 6 demo bookings</span>
        <div class="flex gap-2">
            <span class="rounded-lg bg-[#02143C] px-4 py-2 text-sm font-semibold text-white">1</span>
            <span class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-500">2</span>
            <span class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-500">3</span>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('booking-search');
    const rows = document.querySelectorAll('.booking-row');

    if (!search) return;

    search.addEventListener('input', function () {
        const query = search.value.trim().toLowerCase();

        rows.forEach(function (row) {
            const bookingId = row.dataset.bookingId || '';
            const customer = row.dataset.customer || '';

            row.hidden = !(
                bookingId.includes(query) ||
                customer.includes(query)
            );

            row.style.display = row.hidden ? 'none' : '';
        });
    });
});
</script>

@endsection

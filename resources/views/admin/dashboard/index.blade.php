@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-8">

    {{-- Page Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[#02143C]">
                Dashboard Overview
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Royal Travel Platform performance overview
            </p>
        </div>

        <span class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-500">
            {{ now()->format('d M Y') }}
        </span>
    </div>

    {{-- Statistics --}}
    @php
        $stats = [
            ['label' => 'Total Bookings', 'value' => $overview['totalBookings']],
            ['label' => 'Today Bookings', 'value' => $overview['todayBookings']],
            ['label' => 'Pending Bookings', 'value' => $overview['pendingBookings']],
            ['label' => 'Confirmed Bookings', 'value' => $overview['confirmedBookings']],
            ['label' => 'Total Orders', 'value' => $overview['totalOrders']],
            ['label' => 'Registered Companies', 'value' => $overview['totalCompanies']],
            ['label' => 'Pending Refunds', 'value' => $overview['pendingRefunds']],
        ];
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-slate-500">
                    {{ $stat['label'] }}
                </p>

                <p class="mt-4 text-3xl font-bold text-[#02143C]">
                    {{ number_format($stat['value']) }}
                </p>
            </div>
        @endforeach
    </div>

    {{-- Recent Bookings --}}
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-6 py-5">
            <h2 class="text-lg font-bold text-[#02143C]">
                Recent Bookings
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Latest bookings across B2B and B2C channels
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-6 py-4">Booking ID</th>
                        <th class="px-6 py-4">Service</th>
                        <th class="px-6 py-4">Channel</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Approved Total</th>
                        <th class="px-6 py-4">Date</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse ($overview['recentBookings'] as $booking)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-semibold text-[#02143C]">
                                {{ $booking->booking_no }}
                            </td>

                            <td class="px-6 py-4 capitalize text-slate-600">
                                {{ $booking->service_type }}
                            </td>

                            <td class="px-6 py-4 uppercase text-slate-600">
                                {{ $booking->channel }}
                            </td>

                            <td class="px-6 py-4">
                                <span class="rounded-lg bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                                    {{ str_replace('_', ' ', ucfirst($booking->status)) }}
                                </span>
                            </td>

                            <td class="px-6 py-4 font-medium text-slate-700">
                                @if ($booking->approved_total !== null)
                                    {{ number_format((float) $booking->approved_total, 2) }}
                                    {{ $booking->currency }}
                                @else
                                    —
                                @endif
                            </td>

                            <td class="px-6 py-4 text-slate-500">
                                {{ \Illuminate\Support\Carbon::parse($booking->created_at)->format('d M Y') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                No bookings available yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
@extends('b2b.layouts.app')

@section('title', 'Hotel Offline Requests')

@section('content')
@php
    $stats = [
        ['label' => 'Total Requests', 'value' => '250', 'color' => '#173D8F', 'icon' => '▦'],
        ['label' => 'New Requests', 'value' => '10', 'color' => '#168454', 'icon' => '✚'],
        ['label' => 'Pending Quotation', 'value' => '20', 'color' => '#C79613', 'icon' => '▣'],
        ['label' => 'Quotation Sent', 'value' => '100', 'color' => '#4A91A5', 'icon' => '➤'],
    ];

    $requests = [
        ['id' => 'RB-1048', 'customer' => 'John Doe', 'hotel' => '5 Season', 'destination' => 'Istanbul, Turkey', 'travel' => 'May 23, 2025', 'guests' => '2 Adults', 'children' => '2 Children', 'status' => 'Pending', 'updated' => '20 Nov 2024'],
        ['id' => 'RB-1049', 'customer' => 'Sarah Ahmed', 'hotel' => 'Grand Palace', 'destination' => 'Dubai, UAE', 'travel' => 'Jun 12, 2025', 'guests' => '2 Adults', 'children' => '0 Children', 'status' => 'Quotation Sent', 'updated' => '21 Nov 2024'],
        ['id' => 'RB-1050', 'customer' => 'Michael Lee', 'hotel' => 'Royal Suites', 'destination' => 'Riyadh, Saudi Arabia', 'travel' => 'Jul 05, 2025', 'guests' => '3 Adults', 'children' => '1 Child', 'status' => 'New', 'updated' => '22 Nov 2024'],
        ['id' => 'RB-1051', 'customer' => 'Emma Wilson', 'hotel' => 'City View Hotel', 'destination' => 'Paris, France', 'travel' => 'Aug 18, 2025', 'guests' => '2 Adults', 'children' => '2 Children', 'status' => 'Cancelled', 'updated' => '23 Nov 2024'],
        ['id' => 'RB-1052', 'customer' => 'Omar Hassan', 'hotel' => '5 Season', 'destination' => 'Istanbul, Turkey', 'travel' => 'Sep 10, 2025', 'guests' => '2 Adults', 'children' => '0 Children', 'status' => 'Pending', 'updated' => '24 Nov 2024'],
    ];
@endphp

<div class="mx-auto max-w-7xl space-y-7">

    <div>
        <h2 class="text-2xl font-bold text-[#02143C]">Hotel Offline Requests</h2>
    </div>

    {{-- Statistics --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $index => $stat)
            <div class="relative min-h-44 overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg text-lg font-bold text-white"
                          style="background-color: {{ $stat['color'] }}">
                        {{ $stat['icon'] }}
                    </span>
                    <span class="text-xs font-semibold text-slate-800">{{ $stat['label'] }}</span>
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <span class="text-2xl font-bold text-slate-950">{{ $stat['value'] }}</span>
                    <span class="text-xs font-semibold text-blue-600">↗ 10%</span>
                </div>

                <p class="mt-1 text-[11px] font-medium text-slate-700">+10 More than last month</p>

                <svg viewBox="0 0 130 55" class="absolute bottom-0 end-3 h-16 w-32" aria-hidden="true">
                    <defs>
                        <linearGradient id="request-gradient-{{ $index }}" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="{{ $stat['color'] }}" stop-opacity=".65"/>
                            <stop offset="100%" stop-color="{{ $stat['color'] }}" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0 35 L12 16 L25 23 L38 13 L51 30 L64 18 L77 25 L90 8 L103 22 L116 15 L130 30 L130 55 L0 55 Z"
                          fill="url(#request-gradient-{{ $index }})"/>
                </svg>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">

            <div>
                <label for="request-search" class="mb-2 block text-sm font-semibold text-slate-700">Search</label>
                <input id="request-search" type="search" placeholder="Search requests..."
                       class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-[#B7731E]">
            </div>

            <div>
                <label for="request-status" class="mb-2 block text-sm font-semibold text-slate-700">Status</label>
                <select id="request-status" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                    <option>All Statuses</option>
                    <option>New</option>
                    <option>Pending</option>
                    <option>Quotation Sent</option>
                    <option>Cancelled</option>
                </select>
            </div>

            <div>
                <label for="request-destination" class="mb-2 block text-sm font-semibold text-slate-700">Destination</label>
                <select id="request-destination" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                    <option>All Destinations</option>
                    <option>Istanbul</option>
                    <option>Dubai</option>
                    <option>Riyadh</option>
                    <option>Paris</option>
                </select>
            </div>

            <div>
                <label for="customer-type" class="mb-2 block text-sm font-semibold text-slate-700">Customer Type</label>
                <select id="customer-type" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                    <option>All</option>
                    <option>Individual</option>
                    <option>Corporate</option>
                    <option>Travel Agency</option>
                </select>
            </div>

            <div>
                <label for="date-range" class="mb-2 block text-sm font-semibold text-slate-700">Date Range</label>
                <input id="date-range" type="text" readonly value="10/10/2026 - 19/10/2026"
                       class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
            </div>
        </div>

        <div class="mt-7 flex justify-end gap-3">
            <button type="button" class="rounded-xl border border-[#B7731E] px-8 py-3 text-sm font-semibold text-[#B7731E]">
                Reset
            </button>
            <button type="button" class="rounded-xl bg-[#B7731E] px-8 py-3 text-sm font-semibold text-white">
                Apply Filter
            </button>
        </div>
    </section>

    {{-- Requests Table --}}
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-5">
            <h3 class="text-base font-bold text-[#02143C]">Booking</h3>

            <div class="flex flex-wrap items-center gap-4">
                <label for="request-sort" class="text-sm font-semibold text-slate-600">Sort By:</label>
                <select id="request-sort" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm">
                    <option>Booking Date (Most First)</option>
                    <option>Booking Date (Oldest First)</option>
                </select>
                <button type="button" class="rounded-xl border border-[#B7731E] px-6 py-2.5 text-sm font-semibold text-[#B7731E]">
                    Export
                </button>
            </div>
        </div>

        <div class="overflow-x-auto px-4 pb-4">
            <table class="w-full min-w-[1050px] text-sm">
                <thead class="bg-[#F4F5F7] text-slate-700">
                    <tr>
                        <th class="rounded-s-xl px-4 py-4 text-start">Request ID</th>
                        <th class="px-4 py-4 text-start">Customer</th>
                        <th class="px-4 py-4 text-start">Hotel / Destination</th>
                        <th class="px-4 py-4 text-start">Travel Date</th>
                        <th class="px-4 py-4 text-start">Room & Guests</th>
                        <th class="px-4 py-4 text-start">Status</th>
                        <th class="px-4 py-4 text-start">Last Update</th>
                        <th class="rounded-e-xl px-4 py-4 text-start">Action</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @foreach ($requests as $request)
                        <tr>
                            <td class="px-4 py-5 font-semibold text-[#02143C]">
                                <a href="{{ url('/ui-preview/b2b/bookings/' . $request['id']) }}"
                                   class="hover:text-[#B7731E] hover:underline">
                                    {{ $request['id'] }}
                                </a>
                            </td>
                            <td class="px-4 py-5 text-slate-700">{{ $request['customer'] }}</td>
                            <td class="px-4 py-5">
                                <p class="font-semibold text-slate-800">{{ $request['hotel'] }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ $request['destination'] }}</p>
                            </td>
                            <td class="px-4 py-5 text-slate-600">{{ $request['travel'] }}</td>
                            <td class="px-4 py-5">
                                <p class="text-slate-700">{{ $request['guests'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $request['children'] }}</p>
                            </td>
                            <td class="px-4 py-5">
                                @php
                                    $statusClass = match ($request['status']) {
                                        'New' => 'bg-blue-50 text-blue-700',
                                        'Pending' => 'bg-amber-50 text-amber-700',
                                        'Quotation Sent' => 'bg-emerald-50 text-emerald-700',
                                        default => 'bg-rose-50 text-rose-700',
                                    };
                                @endphp
                                <span class="rounded-lg px-3 py-1.5 text-xs font-semibold {{ $statusClass }}">
                                    {{ $request['status'] }}
                                </span>
                            </td>
                            <td class="px-4 py-5 text-slate-600">{{ $request['updated'] }}</td>
                            <td class="px-4 py-5">
                                <div class="flex gap-2 text-slate-500">
                                    <span class="rounded-lg border border-slate-200 px-2 py-1">✎</span>
                                    <span class="rounded-lg border border-slate-200 px-2 py-1">♲</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- Pagination preview --}}
    <div class="flex flex-wrap items-center justify-between gap-4 text-sm text-slate-600">
        <span>‹ Previous</span>
        <div class="flex items-center gap-2">
            <span class="rounded-lg bg-[#02143C] px-3 py-2 text-white">1</span>
            <span class="rounded-lg border border-slate-200 px-3 py-2">2</span>
            <span class="rounded-lg border border-slate-200 px-3 py-2">3</span>
            <span class="rounded-lg border border-slate-200 px-3 py-2">4</span>
            <span>...</span>
        </div>
        <span>Next ›</span>
    </div>

</div>
@endsection

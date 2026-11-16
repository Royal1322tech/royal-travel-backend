@extends('b2b.layouts.app')

@section('title', 'Dashboard')

@section('content')

@php
    $stats = [
        ['label' => 'Total Bookings', 'value' => '250', 'change' => '+12.8%', 'symbol' => '▦'],
        ['label' => 'Confirmed', 'value' => '180', 'change' => '+8.2%', 'symbol' => '✓'],
        ['label' => 'Pending', 'value' => '45', 'change' => '+3.4%', 'symbol' => '◷'],
        ['label' => 'Total Sales', 'value' => '$24,580', 'change' => '+15.6%', 'symbol' => '$'],
    ];

    $bookings = [
        ['id' => 'RB-1048', 'customer' => 'John Doe', 'service' => 'Hotel', 'date' => 'Oct 21, 2026', 'amount' => '$420', 'payment' => 'Paid', 'status' => 'Confirmed'],
        ['id' => 'RB-1049', 'customer' => 'Sarah Ahmed', 'service' => 'Transportation', 'date' => 'Oct 20, 2026', 'amount' => '$180', 'payment' => 'Pending', 'status' => 'Pending'],
        ['id' => 'RB-1050', 'customer' => 'Michael Lee', 'service' => 'Apartment', 'date' => 'Oct 19, 2026', 'amount' => '$650', 'payment' => 'Paid', 'status' => 'Confirmed'],
        ['id' => 'RB-1051', 'customer' => 'Emma Wilson', 'service' => 'Flight', 'date' => 'Oct 18, 2026', 'amount' => '$320', 'payment' => 'Refunded', 'status' => 'Cancelled'],
    ];
@endphp

<div class="mx-auto max-w-7xl space-y-7">

    <div>
        <h2 class="text-2xl font-bold tracking-tight text-[#02143C]">Dashboard Overview</h2>
        <p class="mt-1 text-sm text-slate-500">Welcome back! Here's an overview of your business activity.</p>
    </div>

    {{-- Statistics --}}
    @php
        $cardColors = [
            ['color' => '#173D8F', 'light' => '#EAF0FC'],
            ['color' => '#168454', 'light' => '#E6F5ED'],
            ['color' => '#C79613', 'light' => '#FFF6DC'],
            ['color' => '#4A91A5', 'light' => '#E7F4F7'],
        ];
    @endphp

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $index => $stat)
            @php
                $palette = $cardColors[$index];
                $gradientId = 'stat-gradient-' . $index;
            @endphp

            <div class="relative min-h-44 overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg text-lg font-bold text-white"
                          style="background-color: {{ $palette['color'] }}">
                        {{ $stat['symbol'] }}
                    </span>
                    <span class="text-xs font-semibold text-slate-800">
                        {{ $stat['label'] }}
                    </span>
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <span class="text-2xl font-bold tracking-tight text-slate-950">
                        {{ $stat['value'] }}
                    </span>
                    <span class="text-xs font-semibold text-blue-600">
                        ↗ {{ $stat['change'] }}
                    </span>
                </div>

                <p class="mt-1 text-[11px] font-medium text-slate-700">
                    +10 More than last month
                </p>

                <svg viewBox="0 0 130 55"
                     class="absolute bottom-0 end-3 h-16 w-32"
                     aria-hidden="true">
                    <defs>
                        <linearGradient id="{{ $gradientId }}" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="{{ $palette['color'] }}" stop-opacity=".65"/>
                            <stop offset="100%" stop-color="{{ $palette['color'] }}" stop-opacity="0"/>
                        </linearGradient>
                    </defs>

                    <path d="M0 35 L12 16 L25 23 L38 13 L51 30 L64 18 L77 25 L90 8 L103 22 L116 15 L130 30 L130 55 L0 55 Z"
                          fill="url(#{{ $gradientId }})"/>

                    <path d="M0 35 L12 16 L25 23 L38 13 L51 30 L64 18 L77 25 L90 8 L103 22 L116 15 L130 30"
                          fill="none"
                          stroke="{{ $palette['color'] }}"
                          stroke-width="1.5"/>
                </svg>
            </div>
        @endforeach
    </div>

    {{-- Charts --}}
    <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">

        <section class="rounded-2xl border border-slate-100 bg-white p-6 xl:col-span-2">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold text-[#02143C]">Booking Performance</h3>
                    <p class="mt-1 text-xs text-slate-400">Monthly booking activity</p>
                </div>
                <span class="rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-500">Last 6 Months</span>
            </div>

            {{-- Static line chart preview --}}
            <div class="mt-6">
                <svg viewBox="0 0 680 300" class="h-auto w-full" role="img"
                     aria-label="Monthly booking performance chart">

                    {{-- Grid lines --}}
                    <g stroke="#E8ECF2" stroke-width="1">
                        <line x1="55" y1="35" x2="650" y2="35"/>
                        <line x1="55" y1="95" x2="650" y2="95"/>
                        <line x1="55" y1="155" x2="650" y2="155"/>
                        <line x1="55" y1="215" x2="650" y2="215"/>

                        <line x1="55" y1="35" x2="55" y2="235"/>
                        <line x1="174" y1="35" x2="174" y2="235"/>
                        <line x1="293" y1="35" x2="293" y2="235"/>
                        <line x1="412" y1="35" x2="412" y2="235"/>
                        <line x1="531" y1="35" x2="531" y2="235"/>
                        <line x1="650" y1="35" x2="650" y2="235"/>
                    </g>

                    {{-- Blue line --}}
                    <path d="M55 125 C95 132 135 150 174 145 S253 137 293 115 S373 76 412 62 S492 38 531 55 S610 85 650 105"
                          fill="none" stroke="#3048E8" stroke-width="3"
                          stroke-linecap="round"/>

                    {{-- Gold line --}}
                    <path d="M55 215 C95 205 135 193 174 183 S253 168 293 159 S373 151 412 145 S492 140 531 130 S610 119 650 108"
                          fill="none" stroke="#B7731E" stroke-width="3"
                          stroke-linecap="round"/>

                    {{-- Data points --}}
                    <g fill="#3048E8" stroke="white" stroke-width="2">
                        <circle cx="55" cy="125" r="5"/>
                        <circle cx="174" cy="145" r="5"/>
                        <circle cx="293" cy="115" r="5"/>
                        <circle cx="412" cy="62" r="5"/>
                        <circle cx="531" cy="55" r="5"/>
                        <circle cx="650" cy="105" r="5"/>
                    </g>

                    <g fill="#B7731E" stroke="white" stroke-width="2">
                        <circle cx="55" cy="215" r="5"/>
                        <circle cx="174" cy="183" r="5"/>
                        <circle cx="293" cy="159" r="5"/>
                        <circle cx="412" cy="145" r="5"/>
                        <circle cx="531" cy="130" r="5"/>
                        <circle cx="650" cy="108" r="5"/>
                    </g>

                    {{-- Month labels --}}
                    <g fill="#94A3B8" font-size="13" text-anchor="middle">
                        <text x="55" y="258">Jan</text>
                        <text x="174" y="258">Feb</text>
                        <text x="293" y="258">Mar</text>
                        <text x="412" y="258">Apr</text>
                        <text x="531" y="258">May</text>
                        <text x="650" y="258">Jun</text>
                    </g>
                </svg>

                <div class="mt-2 flex items-center gap-6 text-xs text-slate-500">
                    <span class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-[#3048E8]"></span>
                        Bookings
                    </span>
                    <span class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-[#B7731E]"></span>
                        Sales
                    </span>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-100 bg-white p-6">
            <h3 class="text-base font-bold text-[#02143C]">Bookings by Service</h3>
            <p class="mt-1 text-xs text-slate-400">Service distribution</p>

            <div class="mx-auto mt-8 flex h-44 w-44 items-center justify-center rounded-full"
                style="background: conic-gradient(#02143C 0% 48%, #B7731E 48% 73%, #92A4C2 73% 90%, #E8EAF0 90% 100%);">
                <div class="flex h-32 w-32 flex-col items-center justify-center rounded-full bg-white">
                    <span class="text-3xl font-bold text-[#02143C]">250</span>
                    <span class="text-xs text-slate-400">Total Bookings</span>
                </div>
            </div>

            <div class="mt-8 space-y-3 text-sm">
                @foreach ([
                    ['Hotels', '48%', '#02143C'],
                    ['Transportation', '25%', '#B7731E'],
                    ['Apartments', '17%', '#92A4C2'],
                    ['Flights', '10%', '#E8EAF0'],
                ] as $service)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $service[2] }}"></span>
                            <span class="text-slate-500">{{ $service[0] }}</span>
                        </div>
                        <span class="font-semibold text-[#02143C]">{{ $service[1] }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    {{-- Recent Bookings --}}
    <section class="overflow-hidden rounded-2xl border border-slate-100 bg-white">
        <div class="flex items-center justify-between px-6 py-5">
            <div>
                <h3 class="text-base font-bold text-[#02143C]">Recent Bookings</h3>
                <p class="mt-1 text-xs text-slate-400">Latest booking activity</p>
            </div>
            <span class="text-sm font-semibold text-[#B7731E]">View All</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[850px] text-start text-sm">
                <thead class="bg-[#F8F9FC] text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-6 py-4 text-start font-semibold">Booking ID</th>
                        <th class="px-6 py-4 text-start font-semibold">Customer</th>
                        <th class="px-6 py-4 text-start font-semibold">Service</th>
                        <th class="px-6 py-4 text-start font-semibold">Date</th>
                        <th class="px-6 py-4 text-start font-semibold">Amount</th>
                        <th class="px-6 py-4 text-start font-semibold">Payment</th>
                        <th class="px-6 py-4 text-start font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($bookings as $booking)
                        <tr>
                            <td class="px-6 py-4 font-semibold text-[#02143C]">{{ $booking['id'] }}</td>
                            <td class="px-6 py-4 text-slate-600">{{ $booking['customer'] }}</td>
                            <td class="px-6 py-4 text-slate-600">{{ $booking['service'] }}</td>
                            <td class="px-6 py-4 text-slate-500">{{ $booking['date'] }}</td>
                            <td class="px-6 py-4 font-semibold text-[#02143C]">{{ $booking['amount'] }}</td>
                            <td class="px-6 py-4 text-slate-500">{{ $booking['payment'] }}</td>
                            <td class="px-6 py-4">
                                <span class="rounded-full px-3 py-1 text-xs font-semibold
                                    {{ $booking['status'] === 'Confirmed' ? 'bg-emerald-50 text-emerald-700' : ($booking['status'] === 'Pending' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700') }}">
                                    {{ $booking['status'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

</div>
@endsection

@extends('b2b.layouts.app')

@section('title', 'Booking Detail')

@section('content')
@php
    $previewHotels = [
        'RB-1048' => ['hotel' => '5 Season', 'location' => 'Istanbul, Turkey'],
        'RB-1049' => ['hotel' => 'Grand Palace', 'location' => 'Dubai, UAE'],
        'RB-1050' => ['hotel' => 'Royal Suites', 'location' => 'Riyadh, Saudi Arabia'],
        'RB-1051' => ['hotel' => 'City View Hotel', 'location' => 'Paris, France'],
        'RB-1052' => ['hotel' => '5 Season', 'location' => 'Istanbul, Turkey'],
    ];

    $previewHotel = $previewHotels[$bookingId] ?? abort(404);

    $previewDetails = [
        'RB-1048' => ['check_in' => '23/05/2025', 'check_out' => '26/05/2025', 'adults' => 2, 'children' => 2, 'status' => 'Pending', 'payment' => 'Pending'],
        'RB-1049' => ['check_in' => '12/06/2025', 'check_out' => '15/06/2025', 'adults' => 2, 'children' => 0, 'status' => 'Quotation Sent', 'payment' => 'Pending'],
        'RB-1050' => ['check_in' => '05/07/2025', 'check_out' => '08/07/2025', 'adults' => 3, 'children' => 1, 'status' => 'New', 'payment' => 'Pending'],
        'RB-1051' => ['check_in' => '18/08/2025', 'check_out' => '21/08/2025', 'adults' => 2, 'children' => 2, 'status' => 'Cancelled', 'payment' => 'Refunded'],
        'RB-1052' => ['check_in' => '10/09/2025', 'check_out' => '13/09/2025', 'adults' => 2, 'children' => 0, 'status' => 'Pending', 'payment' => 'Pending'],
    ];

    $details = $previewDetails[$bookingId] ?? abort(404);

    $previewUpdatedDates = [
        'RB-1048' => '20 Nov 2024',
        'RB-1049' => '21 Nov 2024',
        'RB-1050' => '22 Nov 2024',
        'RB-1051' => '23 Nov 2024',
        'RB-1052' => '24 Nov 2024',
    ];

    $updatedDate = $previewUpdatedDates[$bookingId] ?? abort(404);

    $booking = [
        'id' => $bookingId,
        'hotel' => $previewHotel['hotel'],
        'location' => $previewHotel['location'],
        'check_in' => $details['check_in'],
        'check_out' => $details['check_out'],
        'adults' => $details['adults'],
        'children' => $details['children'],
        'rooms' => 1,
        'room_type' => 'Deluxe Room, City View',
        'meal_plan' => 'Bed & Breakfast',
        'confirmation' => 'DEMO-' . $bookingId,
        'updated_date' => $updatedDate,
        'status' => $details['status'],
        'payment' => $details['payment'],
    ];
@endphp

<div class="mx-auto max-w-7xl space-y-6">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-3 text-lg font-semibold">
        <a href="{{ url('/ui-preview/b2b/bookings') }}"
           class="text-slate-500 hover:text-[#B7731E]">Booking</a>
        <span class="text-slate-400">›</span>
        <span class="text-[#02143C]">Booking Detail</span>
    </nav>

    {{-- Booking Header --}}
    <section class="flex flex-wrap items-center gap-5 rounded-2xl border border-slate-200 bg-white p-5">
        <a href="{{ url('/ui-preview/b2b/bookings') }}"
           class="flex h-11 w-11 items-center justify-center rounded-full bg-[#B7731E] text-2xl text-white"
           aria-label="Back to bookings">‹</a>

        <div class="flex h-20 w-28 items-center justify-center rounded-xl bg-slate-100 text-3xl">🏨</div>

        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-bold text-[#02143C]">{{ $booking['id'] }}</h2>
                <button type="button"
                        onclick="navigator.clipboard.writeText(@js($booking['id'])).then(() => alert('Booking ID copied'))"
                        class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm hover:bg-slate-200"
                        aria-label="Copy booking ID">⧉</button>
            </div>
            <p class="mt-3 text-sm text-slate-600">🛏 Hotel Booking &nbsp; | &nbsp; Last Updated {{ $booking['updated_date'] }}</p>
        </div>

        @php
            $statusColors = [
                'Confirmed' => 'bg-emerald-50 text-emerald-700',
                'Pending' => 'bg-amber-50 text-amber-700',
                'New' => 'bg-blue-50 text-blue-700',
                'Quotation Sent' => 'bg-sky-50 text-sky-700',
                'Cancelled' => 'bg-rose-50 text-rose-700',
            ];

            $paymentColors = [
                'Paid' => 'bg-indigo-50 text-indigo-700',
                'Pending' => 'bg-amber-50 text-amber-700',
                'Refunded' => 'bg-slate-100 text-slate-700',
            ];
        @endphp

        <div class="flex gap-3">
            <span class="rounded-lg px-4 py-2 text-xs font-semibold {{ $paymentColors[$booking['payment']] ?? 'bg-slate-100 text-slate-700' }}">
                {{ $booking['payment'] }}
            </span>
            <span class="rounded-lg px-4 py-2 text-xs font-semibold {{ $statusColors[$booking['status']] ?? 'bg-slate-100 text-slate-700' }}">
                {{ $booking['status'] }}
            </span>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Main Content --}}
        <div class="space-y-6 lg:col-span-2">

            {{-- Hotel Information --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-6">
                <h3 class="mb-6 text-xl font-bold text-slate-950">Hotel Information</h3>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="flex min-h-52 items-center justify-center rounded-xl bg-slate-100 text-6xl">🏨</div>

                    <div>
                        <h4 class="text-xl font-bold text-slate-950">{{ $booking['hotel'] }}</h4>
                        <p class="mt-3 text-sm text-slate-500">📍 {{ $booking['location'] }}</p>
                        <p class="mt-3 text-xl text-amber-500">★★★<span class="text-slate-300">★★</span></p>

                        <div class="mt-5 grid grid-cols-3 gap-3 text-xs text-slate-700">
                            <div>📅 Date<br><strong>{{ $booking['check_in'] }}</strong><br>{{ $booking['check_out'] }}</div>
                            <div>◷ 3 Nights<br><strong>{{ $booking['rooms'] }} Room</strong></div>
                            <div>♧ {{ $booking['adults'] }} Adults<br><strong>{{ $booking['children'] }} Children</strong></div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-3">
                    <div>
                        <p class="text-xs text-slate-500">Room Type</p>
                        <p class="mt-2 text-sm font-semibold">{{ $booking['room_type'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Meal Plan</p>
                        <p class="mt-2 text-sm font-semibold">{{ $booking['meal_plan'] }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">
                        <strong>Cancellation Policy</strong>
                        <p class="mt-1 text-xs">Cancellation policy not available</p>
                    </div>
                </div>
            </section>

            {{-- Booking Timeline --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-6">
                <h3 class="mb-6 text-lg font-bold text-[#02143C]">Booking Activity</h3>

                @php
                    $events = [
                        ['Booking Created', 'The booking request was created.'],
                    ];

                    if ($booking['status'] === 'Quotation Sent') {
                        $events[] = ['Quotation Sent', 'A quotation was sent to the customer.'];
                    }

                    if ($booking['payment'] === 'Paid') {
                        $events[] = ['Payment Received', 'Payment recorded for this booking.'];
                    }

                    if ($booking['status'] === 'Confirmed') {
                        $events[] = ['Supplier Confirmed', 'The supplier confirmed the booking.'];
                    }

                    if ($booking['status'] === 'Cancelled') {
                        $events[] = ['Booking Cancelled', 'The booking request was cancelled.'];
                    }

                    if ($booking['payment'] === 'Refunded') {
                        $events[] = ['Refund Recorded', 'The payment is marked as refunded.'];
                    }

                    if (in_array($booking['status'], ['New', 'Pending'], true)) {
                        $events[] = ['Awaiting Processing', 'The request is awaiting the next action.'];
                    }
                @endphp

                @foreach ($events as $event)
                    <div class="flex gap-4 pb-8 last:pb-0">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-800">✓</span>
                        <div class="flex-1">
                            <div class="flex flex-wrap justify-between gap-2">
                                <h4 class="font-semibold text-slate-900">{{ $event[0] }}</h4>
                                <span class="text-xs text-slate-500">{{ $booking['updated_date'] }}</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-500">{{ $event[1] }}</p>
                        </div>
                    </div>
                @endforeach
            </section>
        </div>

        {{-- Right Column --}}
        <div class="space-y-5">

            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="text-xl font-bold">Booking Status</h3>
                <div class="mt-4 rounded-xl p-4 text-sm {{ $statusColors[$booking['status']] ?? 'bg-slate-100 text-slate-700' }}">
                    <strong>{{ $booking['status'] }}</strong>
                    <p class="mt-1">
                        @switch($booking['status'])
                            @case('Confirmed')
                                Booking confirmed by supplier
                                @break
                            @case('Pending')
                                Booking request is awaiting processing
                                @break
                            @case('Quotation Sent')
                                Quotation has been sent to the customer
                                @break
                            @case('Cancelled')
                                Booking request has been cancelled
                                @break
                            @default
                                Booking request has been received
                        @endswitch
                    </p>
                </div>
                <dl class="mt-5 space-y-4 text-sm">
                    <div class="flex justify-between gap-3"><dt>Confirmation NO</dt><dd class="font-semibold">{{ $booking['id'] }}</dd></div>
                    <div class="flex justify-between gap-3">
                        <dt>Booked On</dt>
                        <dd class="font-semibold">Not available</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>Last Updated</dt>
                        <dd class="font-semibold">{{ $booking['updated_date'] }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="flex justify-between">
                    <h3 class="text-lg font-bold">Payment Summary</h3>
                    <span class="rounded-lg px-3 py-1 text-xs font-semibold {{ $paymentColors[$booking['payment']] ?? 'bg-slate-100 text-slate-700' }}">
                        {{ $booking['payment'] }}
                    </span>
                </div>
                <dl class="mt-5 space-y-4 text-sm">
                    <div class="flex justify-between">
                        <dt>Room Rate</dt>
                        <dd>Not quoted</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Taxes & Fees</dt>
                        <dd>Not quoted</dd>
                    </div>
                    <div class="flex justify-between border-t pt-4 text-lg font-bold">
                        <dt>Total Amount</dt>
                        <dd class="text-[#173D8F]">Not available</dd>
                    </div>
                    <div class="flex justify-between pt-3">
                        <dt>Payment Method</dt>
                        <dd>
                            {{ $booking['payment'] === 'Pending' ? 'Not paid' : 'Not available (demo)' }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="mb-5 text-lg font-bold">Quick Actions</h3>

                <div class="grid grid-cols-2 gap-3">
                    @foreach (['View Voucher', 'Download Invoice', 'Modify Booking', 'Cancel Booking'] as $action)
                        @php
                            $disabled = match ($action) {
                                'View Voucher' => $booking['status'] !== 'Confirmed',
                                'Download Invoice' => $booking['payment'] !== 'Paid',
                                'Modify Booking', 'Cancel Booking' => $booking['status'] === 'Cancelled',
                                default => false,
                            };
                        @endphp

                        <button type="button"
                                @if ($disabled) disabled @else onclick="alert(@js($action . ' — Demo preview only'))" @endif
                                class="rounded-xl px-3 py-3 text-xs font-semibold
                                       {{ $disabled
                                           ? 'cursor-not-allowed bg-slate-100 text-slate-400'
                                           : 'bg-[#E9F2F4] text-[#173D8F] hover:bg-[#D7E8EC]' }}">
                            {{ $action }}
                        </button>
                    @endforeach
                </div>

                <button type="button"
                        onclick="alert('Contact Supplier — Demo preview only')"
                        class="mt-4 w-full rounded-xl border border-[#173D8F] px-4 py-3 text-sm font-semibold text-[#173D8F] hover:bg-blue-50">
                    Contact Supplier
                </button>
            </section>
        </div>
    </div>
</div>
@endsection

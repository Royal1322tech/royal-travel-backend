@extends('admin.layouts.app')

@section('content')
<div class="mx-auto max-w-5xl space-y-6 px-4 py-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-widest text-[#B7731E]">Hotel Management / Booking Review</p>
        <h1 class="mt-2 text-2xl font-bold text-[#02143C]">Review your selected room</h1>
        <p class="mt-2 text-sm text-slate-600">Availability was rechecked. No reservation or payment has been made.</p>
    </div>

    @if ($priceChanged)
        <div role="alert" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            The supplier reported a price change. The final customer-facing price must be verified before booking.
        </div>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-label="Booking details">
        <h2 class="text-lg font-bold text-[#02143C]">Stay details</h2>
        <dl class="mt-5 grid gap-5 sm:grid-cols-2">
            <div><dt class="text-xs font-semibold uppercase text-slate-500">Hotel</dt><dd class="mt-1 font-semibold text-slate-900">{{ $hotelName }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-slate-500">Room</dt><dd class="mt-1 font-semibold text-slate-900">{{ $roomName }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-slate-500">Meal plan</dt><dd class="mt-1 text-slate-800">{{ $mealName }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-slate-500">Rooms requested</dt><dd class="mt-1 text-slate-800">{{ $guestRooms }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-slate-500">Check-in</dt><dd class="mt-1 text-slate-800">{{ $checkin }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-slate-500">Check-out</dt><dd class="mt-1 text-slate-800">{{ $checkout }}</dd></div>
        </dl>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-label="Price and conditions">
        <h2 class="text-lg font-bold text-[#02143C]">Price &amp; conditions</h2>
        <div class="mt-4 space-y-3 text-sm text-slate-700">
            <p><strong>Final selling price:</strong> Pending verified pricing.</p>
            <p><strong>Cancellation policy:</strong> Pending verified policy details.</p>
        </div>
        <div class="mt-6 border-t border-slate-200 pt-5">
            <button type="button" disabled aria-disabled="true" class="cursor-not-allowed rounded-xl bg-[#02143C] px-6 py-3 font-semibold text-white opacity-50">
                Confirm Booking — Not Available
            </button>
            <p class="mt-3 text-xs text-slate-500">Booking and payment remain disabled until pricing and booking confirmation are implemented and verified.</p>
        </div>
    </section>
</div>
@endsection

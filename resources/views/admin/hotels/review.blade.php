
@extends('admin.layouts.app')

@section('content')
<div class="mx-auto max-w-5xl space-y-6 px-4 py-6">

    {{-- Page heading --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-widest text-[#B7731E]">
            Hotel Management / Booking Review
        </p>

        <h1 class="mt-2 text-2xl font-bold text-[#02143C]">
            Review your selected room
        </h1>

        <p class="mt-2 text-sm text-slate-600">
            Availability was rechecked. No reservation or payment has been made.
        </p>
    </div>

    {{-- Price change warning --}}
    @if ($priceChanged)
        <div
            role="alert"
            class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
        >
            The supplier reported a price change.
            The final customer-facing price must be verified before booking.
        </div>
    @endif

    {{-- Stay details --}}
    <section
        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
        aria-label="Booking details"
    >
        <h2 class="text-lg font-bold text-[#02143C]">
            Stay details
        </h2>

        <dl class="mt-5 grid gap-5 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase text-slate-500">
                    Hotel
                </dt>
                <dd class="mt-1 font-semibold text-slate-900">
                    {{ $hotelName }}
                </dd>
            </div>

            <div>
                <dt class="text-xs font-semibold uppercase text-slate-500">
                    Room
                </dt>
                <dd class="mt-1 font-semibold text-slate-900">
                    {{ $roomName }}
                </dd>
            </div>

            <div>
                <dt class="text-xs font-semibold uppercase text-slate-500">
                    Meal plan
                </dt>
                <dd class="mt-1 text-slate-800">
                    {{ $mealName }}
                </dd>
            </div>

            <div>
                <dt class="text-xs font-semibold uppercase text-slate-500">
                    Rooms requested
                </dt>
                <dd class="mt-1 text-slate-800">
                    {{ $guestRooms }}
                </dd>
            </div>

            <div>
                <dt class="text-xs font-semibold uppercase text-slate-500">
                    Check-in
                </dt>
                <dd class="mt-1 text-slate-800">
                    {{ $checkin }}
                </dd>
            </div>

            <div>
                <dt class="text-xs font-semibold uppercase text-slate-500">
                    Check-out
                </dt>
                <dd class="mt-1 text-slate-800">
                    {{ $checkout }}
                </dd>
            </div>
        </dl>
    </section>

    {{-- Price and cancellation conditions --}}
    <section
        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
        aria-label="Price and conditions"
    >
        <h2 class="text-lg font-bold text-[#02143C]">
            Price &amp; conditions
        </h2>

        {{-- Final selling price --}}
        <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Final selling price
            </p>

            @if (($priceReview['verified'] ?? false) === true)
                <p class="mt-2 text-xl font-bold text-[#02143C]">
                    {{ $priceReview['amount'] }} {{ $priceReview['currency'] }}
                </p>
                <p class="mt-2 text-xs text-slate-500">
                    Royal selling price for the selected stay. Booking and payment are not yet enabled.
                </p>
            @else
                <p class="mt-2 text-base font-semibold text-amber-800">
                    Price on request
                </p>
                <p class="mt-2 text-xs text-slate-500">
                    Final price is unavailable until supplier total, taxes and fees are verified.
                    No booking or payment has been made.
                </p>
            @endif
        </div>

        {{-- Cancellation policy --}}
        <div class="mt-5 rounded-xl border border-slate-200 p-5">
            <h3 class="text-base font-bold text-[#02143C]">
                Cancellation Policy
            </h3>

            @if (
                isset($cancellationPolicy)
                && is_array($cancellationPolicy)
                && ($cancellationPolicy['verified'] ?? false) === true
            )

                {{-- Free cancellation deadline --}}
                @if (!empty($cancellationPolicy['free_cancellation_until']))

                    @if ($cancellationPolicy['free_cancellation_active'] ?? false)

                        {{-- Free cancellation is still available --}}
                        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                            <p class="font-semibold text-emerald-800">
                                Free cancellation available
                            </p>

                            <p class="mt-1 text-sm text-emerald-700">
                                Cancel before
                                {{ $cancellationPolicy['free_cancellation_until'] }}
                                without a cancellation penalty.
                            </p>
                        </div>

                    @else

                        {{-- Free cancellation deadline has passed --}}
                        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <p class="font-semibold text-amber-900">
                                Free cancellation period has ended
                            </p>

                            <p class="mt-1 text-sm text-amber-800">
                                The free cancellation deadline was
                                {{ $cancellationPolicy['free_cancellation_until'] }}.
                                Cancellation penalties may now apply.
                            </p>
                        </div>

                    @endif

                @endif

                {{-- Cancellation penalty --}}
                @if (!empty($cancellationPolicy['penalty_description']))

                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <p class="font-semibold text-amber-900">
                            Cancellation penalty
                        </p>

                        <p class="mt-1 text-sm text-amber-800">
                            {{ $cancellationPolicy['penalty_description'] }}
                        </p>
                    </div>

                @endif

                {{-- Timezone --}}
                @if (!empty($cancellationPolicy['timezone']))

                    <p class="mt-3 text-xs text-slate-500">
                        All cancellation deadlines are shown in:
                        {{ $cancellationPolicy['timezone'] }}
                    </p>

                @endif

            @else

                {{-- Policy not verified --}}
                <div class="mt-4 rounded-lg bg-slate-50 p-4">
                    <p class="text-sm font-medium text-slate-700">
                        Cancellation policy details are pending verification.
                    </p>

                    <p class="mt-2 text-xs text-slate-500">
                        Cancellation deadlines and penalties will be shown
                        after the supplier policy is fully validated.
                    </p>
                </div>

            @endif
        </div>

        {{-- Booking action --}}
        <div class="mt-6 border-t border-slate-200 pt-5">
            <button
                type="button"
                disabled
                aria-disabled="true"
                class="cursor-not-allowed rounded-xl bg-[#02143C] px-6 py-3 font-semibold text-white opacity-50"
            >
                Confirm Booking — Not Available
            </button>

            <p class="mt-3 text-xs text-slate-500">
                Booking and payment remain disabled until pricing,
                cancellation conditions, and booking confirmation
                are fully implemented and verified.
            </p>
        </div>
    </section>

</div>
@endsection

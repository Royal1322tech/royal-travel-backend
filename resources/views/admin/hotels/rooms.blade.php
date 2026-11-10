@extends('admin.layouts.app')

@section('content')
@php
    $mealLabels = [
        'nomeal' => 'Room Only',
        'no_meal' => 'Room Only',
        'room only' => 'Room Only',
        'breakfast' => 'Breakfast Included',
        'breakfast included' => 'Breakfast Included',
        'half-board' => 'Half Board',
        'half_board' => 'Half Board',
        'halfboard' => 'Half Board',
        'full-board' => 'Full Board',
        'full_board' => 'Full Board',
        'fullboard' => 'Full Board',
        'all-inclusive' => 'All Inclusive',
        'all_inclusive' => 'All Inclusive',
        'allinclusive' => 'All Inclusive',
        'ultra-all-inclusive' => 'Ultra All Inclusive',
        'ultra_all_inclusive' => 'Ultra All Inclusive',
    ];
@endphp
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6">
    <a href="{{ url()->previous() }}" class="inline-flex items-center text-sm font-semibold text-[#02143C] hover:underline">← Back to hotel results</a>

    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-widest text-[#B7731E]">Hotel Management / Room Selection</p>
        <h1 class="mt-2 text-2xl font-bold text-[#02143C]">{{ $hotel['name'] ?? 'Hotel rooms' }}</h1>
        @if (!empty($hotel['location']))
            <p class="mt-2 text-sm text-slate-500">{{ $hotel['location'] }}</p>
        @endif
        <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-600">
            <span><strong class="text-[#02143C]">Check-in:</strong> {{ $search['checkin'] ?? '—' }}</span>
            <span><strong class="text-[#02143C]">Check-out:</strong> {{ $search['checkout'] ?? '—' }}</span>
            <span><strong class="text-[#02143C]">Available rate options:</strong> {{ count($rooms) }}</span>
        </div>
        <p class="mt-4 rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-xs leading-relaxed text-amber-800">
            These are search-time rate options, not confirmed offers. Prices, cancellation terms, payment conditions and booking will be available only after pricing integration and RateHawk rate recheck.
        </p>
    </header>

    <section class="space-y-4" aria-label="Available room rate options">
        @forelse ($rooms as $room)
            @php
                $mealRaw = trim((string) ($room['meal'] ?? ''));
                $mealKey = strtolower($mealRaw);
                $mealLabel = $mealLabels[$mealKey] ?? ($mealRaw !== '' ? $mealRaw : 'Meal details pending');
                $roomName = trim((string) ($room['name'] ?? ''));
                $bedding = $room['bedding_type'] ?? null;
                $bathroom = $room['bathroom'] ?? null;
                $allotment = $room['allotment'] ?? null;
                $package = $room['package'] ?? null;
            @endphp
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-5">
                    <div class="min-w-0 flex-1 space-y-3">
                        <div>
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Rate option {{ $loop->iteration }}</p>
                            <h2 class="break-words text-lg font-bold text-[#02143C]">{{ $roomName !== '' ? $roomName : 'Room details pending' }}</h2>
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs text-slate-700">
                            <span class="rounded-lg bg-slate-100 px-3 py-2">{{ $mealLabel }}</span>
                            @if (is_string($bedding) && trim($bedding) !== '')
                                <span class="rounded-lg bg-slate-100 px-3 py-2">Bed: {{ $bedding }}</span>
                            @endif
                            @if (is_string($bathroom) && trim($bathroom) !== '')
                                <span class="rounded-lg bg-slate-100 px-3 py-2">Bathroom: {{ $bathroom }}</span>
                            @endif
                            @if ($package === true)
                                <span class="rounded-lg bg-amber-50 px-3 py-2 text-amber-800">Package rate</span>
                            @elseif ($package === false)
                                <span class="rounded-lg bg-slate-100 px-3 py-2">Non-package rate</span>
                            @endif
                        </div>
                        @if (is_numeric($allotment))
                            <p class="text-xs text-slate-500">Supplier-reported availability: {{ $allotment }} {{ (float) $allotment === 1.0 ? 'room' : 'rooms' }} (subject to recheck)</p>
                        @else
                            <p class="text-xs text-slate-500">Availability must be confirmed during rate recheck.</p>
                        @endif
                        <p class="text-xs text-slate-400">Cancellation and payment terms: pending supplier rate validation.</p>
                    </div>
                    
<form
    method="POST"
    action="{{ route('admin.hotels.rooms.select', ['token' => $token]) }}"
>
    @csrf

    <input
        type="hidden"
        name="rate_index"
        value="{{ $room['rate_index'] }}"
    >

    <button
        type="submit"
        class="rounded-xl bg-[#02143C] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#102B62] focus:outline-none focus:ring-2 focus:ring-[#B7731E] focus:ring-offset-2"
    >
        Select Room
    </button>
</form>

                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-500">No room rates available in this search snapshot. Please run a new hotel search.</div>
        @endforelse
    </section>
</div>
@endsection

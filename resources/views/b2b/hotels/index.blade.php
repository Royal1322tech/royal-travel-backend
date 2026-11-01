@extends('b2b.layouts.app')

@section('title', 'Hotels')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">

    {{-- Hotel Search --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">

            <label class="block">
                <span class="mb-2 block text-sm font-semibold text-[#02143C]">
                    Destination
                </span>
                <input
                    type="text"
                    placeholder="City, destination or hotel"
                    value="Turkish"
                    class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-[#B7731E]"
                >
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-semibold text-[#02143C]">
                    Check In
                </span>
                <input
                    type="date"
                    class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-[#B7731E]"
                >
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-semibold text-[#02143C]">
                    Check Out
                </span>
                <input
                    type="date"
                    class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-[#B7731E]"
                >
            </label>

            <label class="block">
                <span class="mb-2 block text-sm font-semibold text-[#02143C]">
                    Rooms & Guests
                </span>
                <select class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-[#B7731E]">
                    <option>1 Room · 2 Guests</option>
                    <option>2 Rooms · 4 Guests</option>
                    <option>2 Rooms · 9 Guests</option>
                    <option>3 Rooms · 6 Guests</option>
                </select>
            </label>

        </div>

        <div class="mt-5 flex justify-end">
            <button
                type="button"
                class="rounded-xl bg-[#02143C] px-8 py-3 text-sm font-semibold text-white transition hover:bg-[#B7731E]"
            >
                Search Hotels
            </button>
        </div>
    </section>

    {{-- Hotel Filters and Results --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">

        {{-- Filters Sidebar --}}
        <aside class="space-y-6 rounded-2xl border border-slate-200 bg-white p-5 lg:col-span-3">

            <div>
                <h3 class="mb-4 font-bold text-[#02143C]">Top Product</h3>
                <label class="flex items-center gap-3 text-sm">
                    <input type="checkbox" class="accent-[#B7731E]">
                    Luxury Collection
                </label>
            </div>

            <hr class="border-slate-100">

            <div>
                <h3 class="mb-4 font-bold text-[#02143C]">Board</h3>
                <div class="space-y-3">
                    <label class="flex items-center gap-3 text-sm">
                        <input type="checkbox" class="accent-[#B7731E]">
                        Incl. Breakfast
                    </label>
                    <label class="flex items-center gap-3 text-sm">
                        <input type="checkbox" class="accent-[#B7731E]">
                        Rooms Only
                    </label>
                </div>
            </div>

            <hr class="border-slate-100">

            <div>
                <h3 class="mb-4 font-bold text-[#02143C]">Category</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach ([1, 2, 3, 4, 5] as $star)
                        <label class="cursor-pointer">
                            <input type="checkbox" class="peer sr-only">
                            <span class="inline-flex rounded-lg border border-slate-200 px-3 py-2 text-xs peer-checked:border-[#B7731E] peer-checked:bg-amber-50">
                                {{ $star }} ★
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <hr class="border-slate-100">

            <div>
                <h3 class="mb-4 font-bold text-[#02143C]">Customer Review</h3>
                <div class="space-y-3">
                    @foreach (['Very Good 4+', 'Good 3.5+', 'Nice 3+'] as $review)
                        <label class="flex items-center gap-3 text-sm">
                            <input type="checkbox" class="accent-[#B7731E]">
                            {{ $review }}
                        </label>
                    @endforeach
                </div>
            </div>

            <hr class="border-slate-100">

            <div>
                <h3 class="mb-4 font-bold text-[#02143C]">Establishment Profile</h3>
                <div class="space-y-3">
                    @foreach (['Business Hotels', 'Luxury Collection'] as $profile)
                        <label class="flex items-center gap-3 text-sm">
                            <input type="checkbox" class="accent-[#B7731E]">
                            {{ $profile }}
                        </label>
                    @endforeach
                </div>
            </div>

            <hr class="border-slate-100">

            <div>
                <h3 class="mb-4 font-bold text-[#02143C]">Amenities</h3>
                <div class="space-y-3">
                    @foreach (['Adapted for Disabled', 'Air Conditioned', 'Bathroom', 'Gym', 'Heating'] as $amenity)
                        <label class="flex items-center gap-3 text-sm">
                            <input type="checkbox" class="accent-[#B7731E]">
                            {{ $amenity }}
                        </label>
                    @endforeach
                </div>
            </div>

            <hr class="border-slate-100">

            <div>
                <h3 class="mb-4 font-bold text-[#02143C]">Price (USD)</h3>
                <div class="grid grid-cols-2 gap-3">
                    <input type="number" min="0" placeholder="Min"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <input type="number" min="0" placeholder="Max"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </div>
            </div>

            <hr class="border-slate-100">

            <div>
                <h3 class="mb-4 font-bold text-[#02143C]">Product for Packaging</h3>
                <div class="space-y-3">
                    @foreach (['All', 'With Package', 'Without Package'] as $package)
                        <label class="flex items-center gap-3 text-sm">
                            <input type="checkbox" class="accent-[#B7731E]">
                            {{ $package }}
                        </label>
                    @endforeach
                </div>
            </div>

        </aside>

        {{-- Hotel Results --}}
        <section class="space-y-5 lg:col-span-9">
            @php
                $hotels = [
                    [
                        'id' => 1,
                        'name' => 'Eastern Al Montazah Hotel',
                        'location' => 'Alexandria, Egypt',
                        'stars' => 4,
                        'room' => 'Deluxe Room, City View',
                        'board' => 'Breakfast Included',
                    ],
                    [
                        'id' => 2,
                        'name' => 'Royal Palace Hotel',
                        'location' => 'Istanbul, Turkey',
                        'stars' => 5,
                        'room' => 'Superior Double Room',
                        'board' => 'Room Only',
                    ],
                    [
                        'id' => 3,
                        'name' => 'Grand Marina Hotel',
                        'location' => 'Dubai, UAE',
                        'stars' => 5,
                        'room' => 'Deluxe King Room',
                        'board' => 'Breakfast Included',
                    ],
                    [
                        'id' => 4,
                        'name' => 'City View Suites',
                        'location' => 'Riyadh, Saudi Arabia',
                        'stars' => 4,
                        'room' => 'Executive Suite',
                        'board' => 'Room Only',
                    ],
                ];
            @endphp

            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold text-[#02143C]">Available Hotels</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ count($hotels) }} demo properties
                    </p>
                </div>
                <select class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                    <option>Recommended</option>
                    <option>Star Rating</option>
                    <option>Hotel Name</option>
                </select>
            </div>

            @foreach ($hotels as $hotel)
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-5 md:flex-row">

                        <div class="flex h-48 w-full shrink-0 items-center justify-center rounded-xl bg-slate-100 text-6xl md:w-56">
                            🏨
                        </div>

                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-bold text-[#02143C]">
                                {{ $hotel['name'] }}
                            </h3>

                            <p class="mt-2 text-sm text-slate-500">
                                📍 {{ $hotel['location'] }}
                            </p>

                            <p class="mt-3 text-lg tracking-wider text-amber-500">
                                {{ str_repeat('★', $hotel['stars']) }}
                            </p>

                            <div class="mt-4 flex flex-wrap gap-3 text-xs text-slate-600">
                                <span class="rounded-lg bg-slate-50 px-3 py-2">
                                    ✓ Non-Smoking Rooms
                                </span>
                                <span class="rounded-lg bg-slate-50 px-3 py-2">
                                    ✓ Room Service
                                </span>
                                <span class="rounded-lg bg-slate-50 px-3 py-2">
                                    ✓ Air Conditioning
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 pt-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">
                                {{ $hotel['room'] }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $hotel['board'] }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                Room availability and cancellation policy not checked
                            </p>
                        </div>

                        <button
                            type="button"
                            onclick="alert('View Rooms — Demo preview only')"
                            class="rounded-xl bg-[#02143C] px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#B7731E]"
                        >
                            View Rooms
                        </button>
                    </div>
                </article>
            @endforeach
        </section>

    </div>
</div>
@endsection

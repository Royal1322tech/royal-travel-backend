
{{-- Shared Hotel Search & Results Partial --}}
{{-- Used by Admin and B2B dashboards --}}

@php
    $hotels = $hotels ?? [];
    $searchPerformed = $searchPerformed ?? false;
    $searchAction = $searchAction ?? '#';

    $selectedOccupancy = request('occupancy', '1-2');
    $selectedResidency = request('residency', 'eg');
@endphp

<div class="mx-auto max-w-7xl space-y-6">

    {{-- Page Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#B7731E]">
                HOTEL MANAGEMENT
            </p>

            <h1 class="mt-2 text-3xl font-bold text-[#02143C]">
                Hotel Search Results
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                Search hotels, explore available rooms and compare accommodation options.
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
            <span class="font-semibold text-[#02143C]">Supplier:</span>
            <span class="ml-1 text-slate-500">RateHawk Sandbox</span>
        </div>
    </div>

    {{-- Search Form --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">

        <form
            method="GET"
            action="{{ $searchAction }}"
            class="space-y-5"
        >

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">

                {{-- Destination --}}
                <label class="block">
                    <span class="mb-2 block text-sm font-semibold text-[#02143C]">
                        Destination
                    </span>

                    <input
                        type="text"
                        name="destination"
                        value="{{ request('destination', '') }}"
                        placeholder="City, destination or hotel"
                        autocomplete="off"
                        class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-[#B7731E]"
                    >
                </label>

                {{-- Check In --}}
                <label class="block">
                    <span class="mb-2 block text-sm font-semibold text-[#02143C]">
                        Check In
                    </span>

                    <input
                        type="date"
                        name="checkin"
                        value="{{ request('checkin', '') }}"
                        min="{{ now()->toDateString() }}"
                        required
                        class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-[#B7731E]"
                    >
                </label>

                {{-- Check Out --}}
                <label class="block">
                    <span class="mb-2 block text-sm font-semibold text-[#02143C]">
                        Check Out
                    </span>

                    <input
                        type="date"
                        name="checkout"
                        value="{{ request('checkout', '') }}"
                        min="{{ now()->addDay()->toDateString() }}"
                        required
                        class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-[#B7731E]"
                    >
                </label>

                {{-- Residency --}}
                <label class="block">
                    <span class="mb-2 block text-sm font-semibold text-[#02143C]">
                        Residency
                    </span>

                    <select
                        name="residency"
                        class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-[#B7731E]"
                    >
                        <option value="eg" @selected($selectedResidency === 'eg')>
                            Egypt
                        </option>

                        <option value="sa" @selected($selectedResidency === 'sa')>
                            Saudi Arabia
                        </option>

                        <option value="ae" @selected($selectedResidency === 'ae')>
                            United Arab Emirates
                        </option>

                        <option value="gb" @selected($selectedResidency === 'gb')>
                            United Kingdom
                        </option>

                        <option value="us" @selected($selectedResidency === 'us')>
                            United States
                        </option>
                    </select>
                </label>

                {{-- Rooms & Guests --}}
                <label class="block">
                    <span class="mb-2 block text-sm font-semibold text-[#02143C]">
                        Rooms & Guests
                    </span>

                    <select
                        name="occupancy"
                        class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-[#B7731E]"
                    >
                        <option value="1-2" @selected($selectedOccupancy === '1-2')>
                            1 Room · 2 Guests
                        </option>

                        <option value="2-4" @selected($selectedOccupancy === '2-4')>
                            2 Rooms · 4 Guests
                        </option>

                        <option value="3-6" @selected($selectedOccupancy === '3-6')>
                            3 Rooms · 6 Guests
                        </option>
                    </select>
                </label>

            </div>

            {{-- Search Button --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">

                <p class="text-xs text-slate-400">
                    Prices and availability are subject to supplier confirmation.
                </p>

                <button
                    type="submit"
                    class="rounded-xl bg-[#02143C] px-8 py-3 text-sm font-semibold text-white transition hover:bg-[#B7731E]"
                >
                    Search Hotels
                </button>

            </div>

        </form>

    </section>

    {{-- Filters and Results --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">

        {{-- Filters Sidebar --}}
        <aside class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 lg:col-span-3">

            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-[#02143C]">
                    Filters
                </h2>

                <span class="text-xs text-slate-400">
                    Coming soon
                </span>
            </div>

            <hr class="border-slate-100">

            {{-- Hotel Name --}}
            <div>
                <h3 class="mb-3 text-sm font-bold text-[#02143C]">
                    Hotel Name
                </h3>

                <input
                    type="text"
                    name="hotel_name"
                    placeholder="Search hotel name"
                    disabled
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-400"
                >
            </div>

            <hr class="border-slate-100">

            {{-- Area --}}
            <div>
                <h3 class="mb-3 text-sm font-bold text-[#02143C]">
                    Area / Location
                </h3>

                <p class="text-xs text-slate-400">
                    Available after hotel content integration.
                </p>
            </div>

            <hr class="border-slate-100">

            {{-- Top Product --}}
            <div>
                <h3 class="mb-3 text-sm font-bold text-[#02143C]">
                    Top Product
                </h3>

                <label class="flex items-center gap-3 text-sm text-slate-400">
                    <input type="checkbox" disabled>
                    Luxury Collection
                </label>
            </div>

            <hr class="border-slate-100">

            {{-- Board --}}
            <div>
                <h3 class="mb-3 text-sm font-bold text-[#02143C]">
                    Board
                </h3>

                <div class="space-y-3">
                    @foreach ([
                        'Room Only',
                        'Bed & Breakfast',
                        'Half Board',
                        'Full Board',
                        'All Inclusive',
                        'Ultra All Inclusive',
                    ] as $board)

                        <label class="flex items-center gap-3 text-sm text-slate-400">
                            <input type="checkbox" disabled>
                            {{ $board }}
                        </label>

                    @endforeach
                </div>
            </div>

            <hr class="border-slate-100">

            {{-- Category --}}
            <div>
                <h3 class="mb-3 text-sm font-bold text-[#02143C]">
                    Star Category
                </h3>

                <div class="flex flex-wrap gap-2">
                    @foreach ([1, 2, 3, 4, 5] as $star)

                        <span class="rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-400">
                            {{ $star }} ★
                        </span>

                    @endforeach
                </div>
            </div>

            <hr class="border-slate-100">

            {{-- Customer Review --}}
            <div>
                <h3 class="mb-3 text-sm font-bold text-[#02143C]">
                    Customer Review
                </h3>

                <div class="space-y-3">
                    @foreach ([
                        'Very Good 4+',
                        'Good 3.5+',
                        'Nice 3+',
                    ] as $review)

                        <label class="flex items-center gap-3 text-sm text-slate-400">
                            <input type="checkbox" disabled>
                            {{ $review }}
                        </label>

                    @endforeach
                </div>
            </div>

            <hr class="border-slate-100">

            {{-- Establishment Profile --}}
            <div>
                <h3 class="mb-3 text-sm font-bold text-[#02143C]">
                    Establishment Profile
                </h3>

                <div class="space-y-3">
                    @foreach ([
                        'Business Hotels',
                        'Luxury Collection',
                    ] as $profile)

                        <label class="flex items-center gap-3 text-sm text-slate-400">
                            <input type="checkbox" disabled>
                            {{ $profile }}
                        </label>

                    @endforeach
                </div>
            </div>

            <hr class="border-slate-100">

            {{-- Amenities --}}
            <div>
                <h3 class="mb-3 text-sm font-bold text-[#02143C]">
                    Amenities
                </h3>

                <div class="space-y-3">
                    @foreach ([
                        'Non-Smoking Rooms',
                        'Room Service',
                        'Air Conditioning',
                        'Bathroom',
                        'Gym',
                        'Heating',
                    ] as $amenity)

                        <label class="flex items-center gap-3 text-sm text-slate-400">
                            <input type="checkbox" disabled>
                            {{ $amenity }}
                        </label>

                    @endforeach
                </div>
            </div>

            <hr class="border-slate-100">

            {{-- Price --}}
            <div>
                <h3 class="mb-3 text-sm font-bold text-[#02143C]">
                    Price (USD)
                </h3>

                <div class="grid grid-cols-2 gap-3">

                    <input
                        type="number"
                        min="0"
                        placeholder="Min"
                        disabled
                        class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                    >

                    <input
                        type="number"
                        min="0"
                        placeholder="Max"
                        disabled
                        class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                    >

                </div>
            </div>

            <hr class="border-slate-100">

            {{-- Packaging --}}
            <div>
                <h3 class="mb-3 text-sm font-bold text-[#02143C]">
                    Product Packaging
                </h3>

                <div class="space-y-3">
                    @foreach ([
                        'All',
                        'With Package',
                        'Without Package',
                    ] as $package)

                        <label class="flex items-center gap-3 text-sm text-slate-400">
                            <input type="radio" disabled>
                            {{ $package }}
                        </label>

                    @endforeach
                </div>
            </div>

            <button
                type="button"
                disabled
                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-400"
            >
                Reset Filters
            </button>

        </aside>

        {{-- Results Section --}}
        <section class="space-y-5 lg:col-span-9">

            {{-- Results Header --}}
            <div class="flex flex-wrap items-center justify-between gap-4">

                <div>
                    <h2 class="text-xl font-bold text-[#02143C]">
                        Available Hotels
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        @if ($searchPerformed)
                            {{ count($hotels) }} hotel results
                        @else
                            Enter your search details to find available hotels.
                        @endif
                    </p>
                </div>

                {{-- Sorting --}}
                <select
                    disabled
                    class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-400"
                >
                    <option>Recommended</option>
                    <option>Price: Low to High</option>
                    <option>Price: High to Low</option>
                    <option>Star Rating</option>
                    <option>Hotel Name</option>
                </select>

            </div>

            {{-- Hotel Cards --}}
            @forelse ($hotels as $hotel)

                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">

                    <div class="flex flex-col gap-5 md:flex-row">

                        {{-- Hotel Image Placeholder --}}
                        <div class="flex h-48 w-full shrink-0 items-center justify-center rounded-xl bg-slate-100 text-5xl md:w-56">
                            🏨
                        </div>

                        {{-- Hotel Information --}}
                        <div class="min-w-0 flex-1">

                            <h3 class="text-lg font-bold text-[#02143C]">
                                {{ $hotel['name'] ?? 'Hotel information pending' }}
                            </h3>

                            <p class="mt-2 text-sm text-slate-500">
                                {{ $hotel['location'] ?? 'Location unavailable' }}
                            </p>

                            @if (!empty($hotel['stars']))
                                <p class="mt-3 text-lg tracking-wider text-amber-500">
                                    {{ str_repeat('★', (int) $hotel['stars']) }}
                                </p>
                            @endif

                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach (($hotel['amenities'] ?? []) as $amenity)

                                    <span class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                        {{ $amenity }}
                                    </span>

                                @endforeach
                            </div>

                        </div>
                    </div>

                    {{-- Room Preview --}}
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 pt-4">

                        <div>
                            <p class="text-sm font-semibold text-slate-800">
                                {{ $hotel['room'] ?? 'Room details pending' }}
                            </p>

                            <p class="mt-1 text-xs text-slate-500">
                                {{ $hotel['board'] ?? 'Meal plan not supplied' }}
                            </p>

                            <p class="mt-1 text-xs text-slate-500">
                                Rates and availability must be verified before booking.
                            </p>
                        </div>

                        <button
                            type="button"
                            disabled
                            class="cursor-not-allowed rounded-xl bg-slate-300 px-6 py-3 text-sm font-semibold text-white"
                        >
                            View Rooms
                        </button>

                    </div>

                </article>

            @empty

                {{-- Empty State --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm">

                    <div class="mb-4 text-4xl">
                        🏨
                    </div>

                    @if ($searchPerformed)

                        <h3 class="text-lg font-bold text-[#02143C]">
                            No hotels found
                        </h3>

                        <p class="mt-2 text-sm text-slate-500">
                            No hotels are available for your search criteria.
                            Try changing the destination or travel dates.
                        </p>

                    @else

                        <h3 class="text-lg font-bold text-[#02143C]">
                            Start your hotel search
                        </h3>

                        <p class="mt-2 text-sm text-slate-500">
                            Select a destination, travel dates and guests
                            to explore available hotels.
                        </p>

                    @endif

                </div>

            @endforelse

        </section>

    </div>

</div>

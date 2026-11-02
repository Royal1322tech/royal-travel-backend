
@php
    $hotels = $hotels ?? [];
    $searchPerformed = $searchPerformed ?? false;
    $searchAction = $searchAction ?? '#';
    $searchError = $searchError ?? null;
    $total = $total ?? count($hotels);

    $isAdminHotelSearch = request()->routeIs('admin.hotels.index');

    $selectedOccupancy = request('occupancy', '1-2');
    $selectedResidency = request('residency', 'eg');
    $selectedDestination = request('destination', 'dubai');

    // Temporary destinations for Sandbox testing.
    // Later: replace with a destination autocomplete API.
    $destinations = [
        'dubai' => [
            'label' => 'Dubai, United Arab Emirates',
            'latitude' => 25.2048,
            'longitude' => 55.2708,
        ],
        'cairo' => [
            'label' => 'Cairo, Egypt',
            'latitude' => 30.0444,
            'longitude' => 31.2357,
        ],
        'riyadh' => [
            'label' => 'Riyadh, Saudi Arabia',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
        ],
    ];

    $selectedDestinationData = $destinations[$selectedDestination]
        ?? $destinations['dubai'];

    $occupancyOptions = [
        '1-2' => ['rooms' => 1, 'adults' => 2],
        '2-4' => ['rooms' => 2, 'adults' => 4],
        '3-6' => ['rooms' => 3, 'adults' => 6],
    ];
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

    {{-- Validation Errors --}}
    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <p class="font-semibold">Please check your search details:</p>

            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Supplier Error --}}
    @if ($searchError)
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            {{ $searchError }}
        </div>
    @endif

    {{-- Search Form --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">

        <form
            id="hotel-search-form"
            method="GET"
            action="{{ $searchAction }}"
            class="space-y-5"
        >
            @if ($isAdminHotelSearch)
                <input type="hidden" name="search" value="1">
                <input
                    type="hidden"
                    name="latitude"
                    id="hotel-latitude"
                    value="{{ $selectedDestinationData['latitude'] }}"
                >
                <input
                    type="hidden"
                    name="longitude"
                    id="hotel-longitude"
                    value="{{ $selectedDestinationData['longitude'] }}"
                >
                <input type="hidden" name="radius" value="10000">
                <input type="hidden" name="currency" value="USD">
                <input type="hidden" name="language" value="en">
                <input
                    type="hidden"
                    name="adults"
                    id="hotel-adults"
                    value="{{ $occupancyOptions[$selectedOccupancy]['adults'] ?? 2 }}"
                >
            @endif

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">

                {{-- Destination --}}
                <div class="block">
                    <label
                        for="hotel-destination"
                        class="mb-2 block text-sm font-semibold text-[#02143C]"
                    >
                        Destination
                    </label>

                    <select
                        id="hotel-destination"
                        name="destination"
                        required
                        class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-[#B7731E]"
                    >
                        @foreach ($destinations as $key => $destination)
                            <option
                                value="{{ $key }}"
                                data-latitude="{{ $destination['latitude'] }}"
                                data-longitude="{{ $destination['longitude'] }}"
                                @selected($selectedDestination === $key)
                            >
                                {{ $destination['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

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
                        id="hotel-occupancy"
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
                    id="hotel-search-submit"
                    type="submit"
                    class="rounded-xl bg-[#02143C] px-8 py-3 text-sm font-semibold text-white transition hover:bg-[#B7731E] disabled:cursor-wait disabled:opacity-60"
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
                            {{ $total }} hotel results
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
                @php
                    $hotelName = $hotel['name'] ?? ($hotel['id'] ?? 'Hotel information pending');
                    $hotelLocation = $hotel['location'] ?? null;
                    $hotelImage = $hotel['image'] ?? null;
                    $hotelStars = isset($hotel['stars']) && is_numeric($hotel['stars'])
                        ? max(0, min(5, (int) $hotel['stars']))
                        : null;
                    $hotelAmenities = array_slice($hotel['amenities'] ?? [], 0, 5);
                @endphp

                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md">
                    <div class="flex flex-wrap gap-5 p-5" style="display: flex; flex-wrap: wrap; align-items: flex-start; gap: 20px;">
                        {{-- One hotel image only: fixed dimensions independent of generated Tailwind CSS --}}
                        <div class="relative shrink-0 overflow-hidden rounded-xl bg-slate-100" style="width: 240px; height: 190px; max-width: 100%; flex: 0 0 240px;">
                            @if (is_string($hotelImage) && $hotelImage !== '')
                                <img
                                    src="{{ $hotelImage }}"
                                    alt="{{ $hotelName }}"
                                    loading="lazy"
                                    class="h-full w-full object-cover"
                                    style="display: block; width: 100%; height: 100%; object-fit: cover;"
                                >
                            @else
                                <div class="flex h-full w-full items-center justify-center text-center text-sm font-medium text-slate-400" style="display: flex; width: 100%; height: 100%; align-items: center; justify-content: center;">
                                    Hotel image unavailable
                                </div>
                            @endif
                        </div>

                        {{-- Hotel details --}}
                        <div class="min-w-0 flex-1" style="flex: 1 1 260px; min-width: 0;">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <h3 class="break-words text-lg font-bold leading-snug text-[#02143C]">
                                        {{ $hotelName }}
                                    </h3>
                                    <p class="mt-2 text-sm text-slate-500">
                                        {{ $hotelLocation ?: 'Location details pending' }}
                                    </p>
                                </div>

                                @if ($hotelStars !== null && $hotelStars > 0)
                                    <div class="shrink-0 rounded-lg bg-amber-50 px-3 py-2" aria-label="{{ $hotelStars }} star hotel classification">
                                        <span class="text-sm tracking-wide text-amber-500" aria-hidden="true">{{ str_repeat('★', $hotelStars) }}</span>
                                        <span class="ml-1 text-xs font-semibold text-amber-800">{{ $hotelStars }}-star</span>
                                    </div>
                                @endif
                            </div>

                            @if (count($hotelAmenities) > 0)
                                <div class="mt-5 flex flex-wrap gap-2">
                                    @foreach ($hotelAmenities as $amenity)
                                        <span class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                            {{ $amenity }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <p class="mt-4 text-xs text-slate-400">Hotel facilities pending supplier content.</p>
                            @endif
                        </div>
                    </div>

                    {{-- Booking preview: no unverified supplier pricing --}}
                    <div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 bg-slate-50/50 px-5 py-4">
                        <div>
                            <p class="text-sm font-semibold text-[#02143C]">
                                {{ $hotel['room'] ?? 'Room details pending' }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $hotel['board'] ?? 'Meal plan not supplied' }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                Room availability and selling prices will appear after rate validation.
                            </p>
                        </div>
                        <button
                            type="button"
                            disabled
                            title="Room selection is not connected yet"
                            class="cursor-not-allowed rounded-xl bg-slate-200 px-6 py-3 text-sm font-semibold text-slate-500"
                        >
                            View Rooms
                        </button>
                    </div>
                </article>

            @empty

                {{-- Empty State --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm">

                    <div class="mb-4 text-4xl">🏨</div>

                    @if ($searchError)
                        <h3 class="text-lg font-bold text-[#02143C]">
                            Search unavailable
                        </h3>

                        <p class="mt-2 text-sm text-slate-500">
                            Please try again after checking the supplier connection.
                        </p>
                    @elseif ($searchPerformed)
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

@if ($isAdminHotelSearch)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('hotel-search-form');
            const destination = document.getElementById('hotel-destination');
            const latitude = document.getElementById('hotel-latitude');
            const longitude = document.getElementById('hotel-longitude');
            const occupancy = document.getElementById('hotel-occupancy');
            const adults = document.getElementById('hotel-adults');
            const submitButton = document.getElementById('hotel-search-submit');

            const occupancyAdults = {
                '1-2': 2,
                '2-4': 4,
                '3-6': 6
            };

            function updateDestination() {
                const option = destination.options[destination.selectedIndex];

                latitude.value = option.dataset.latitude || '';
                longitude.value = option.dataset.longitude || '';
            }

            function updateOccupancy() {
                adults.value = occupancyAdults[occupancy.value] || 2;
            }

            destination.addEventListener('change', updateDestination);
            occupancy.addEventListener('change', updateOccupancy);

            form.addEventListener('submit', function () {
                updateDestination();
                updateOccupancy();

                if (!form.checkValidity()) {
                    return;
                }

                submitButton.disabled = true;
                submitButton.textContent = 'Searching Hotels...';
            });
        });
    </script>
@endif

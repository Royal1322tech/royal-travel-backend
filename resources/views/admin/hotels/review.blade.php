@extends('admin.layouts.app')

@section('content')
<div class="mx-auto max-w-5xl space-y-6 px-4 py-6">
    {{-- Page heading --}}
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

    {{-- Stay details --}}
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

    {{-- Price and cancellation conditions --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-label="Price and conditions">
        <h2 class="text-lg font-bold text-[#02143C]">Price &amp; conditions</h2>
        <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Final selling price</p>
            @if (($priceReview['verified'] ?? false) === true)
                <p class="mt-2 text-xl font-bold text-[#02143C]">{{ $priceReview['amount'] }} {{ $priceReview['currency'] }}</p>
                <p class="mt-2 text-xs text-slate-500">Royal selling price for the selected stay. Booking and payment are subject to final validation.</p>
            @else
                <p class="mt-2 text-base font-semibold text-amber-800">Price on request</p>
                @if (!empty($priceReview['reason']))
                    <p class="mt-1 text-xs text-slate-500">Internal reason: {{ str_replace('_', ' ', $priceReview['reason']) }}</p>
                @endif
                <p class="mt-2 text-xs text-slate-500">Final price is unavailable until supplier total, taxes and fees are verified. No booking or payment has been made.</p>
            @endif
        </div>

        <div class="mt-5 rounded-xl border border-slate-200 p-5">
            <h3 class="text-base font-bold text-[#02143C]">Cancellation Policy</h3>
            @if (isset($cancellationPolicy) && is_array($cancellationPolicy) && ($cancellationPolicy['verified'] ?? false) === true)
                @if (!empty($cancellationPolicy['free_cancellation_until']))
                    @if ($cancellationPolicy['free_cancellation_active'] ?? false)
                        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                            <p class="font-semibold text-emerald-800">Free cancellation available</p>
                            <p class="mt-1 text-sm text-emerald-700">Cancel before {{ $cancellationPolicy['free_cancellation_until'] }} without a cancellation penalty.</p>
                        </div>
                    @else
                        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <p class="font-semibold text-amber-900">Free cancellation period has ended</p>
                            <p class="mt-1 text-sm text-amber-800">The free cancellation deadline was {{ $cancellationPolicy['free_cancellation_until'] }}. Cancellation penalties may now apply.</p>
                        </div>
                    @endif
                @endif
                @if (!empty($cancellationPolicy['penalty_description']))
                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <p class="font-semibold text-amber-900">Cancellation penalty</p>
                        <p class="mt-1 text-sm text-amber-800">{{ $cancellationPolicy['penalty_description'] }}</p>
                    </div>
                @endif
                @if (!empty($cancellationPolicy['timezone']))
                    <p class="mt-3 text-xs text-slate-500">All cancellation deadlines are shown in: {{ $cancellationPolicy['timezone'] }}</p>
                @endif
            @else
                <div class="mt-4 rounded-lg bg-slate-50 p-4">
                    <p class="text-sm font-medium text-slate-700">Cancellation policy details are pending verification.</p>
                    <p class="mt-2 text-xs text-slate-500">Cancellation deadlines and penalties will be shown after the supplier policy is fully validated.</p>
                </div>
            @endif
        </div>

        {{-- Booking form: requires controller-provided token, companies and roomOccupancies. --}}
        <div class="mt-6 border-t border-slate-200 pt-5">
            <h3 class="text-lg font-bold text-[#02143C]">Complete booking details</h3>
            @if ($errors->any())
                <div role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('draft_saved'))
                <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                    Draft saved: {{ session('draft_saved')['booking_no'] }} — {{ session('draft_saved')['status'] }}.
                    No payment collected and no supplier booking created.
                    <a class="underline font-semibold" href="{{ route('admin.bookings.show', session('draft_saved')['booking_id']) }}">Open Booking Details</a>
                </div>
            @endif
            @if (!empty($token))
                <form method="POST" action="{{ route('admin.hotels.rooms.draft', ['token' => $token]) }}" class="mt-5 space-y-5">
                    @csrf
                    <div>
                        <label for="company_id" class="block text-sm font-semibold text-slate-800">Company (optional — leave blank for direct customer)</label>
                        <select id="company_id" name="company_id" class="mt-2 w-full rounded-xl border border-slate-300 bg-white p-3">
                            <option value="">Direct customer (no company)</option>
                            @foreach (($companies ?? []) as $company)
                                <option value="{{ $company->id }}" @selected(old('company_id') === (string) $company->id)>
                                    {{ $company->name ?? $company->legal_name ?? $company->id }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- One customer selector. Customer and guests are separate entities. --}}
                    <fieldset class="rounded-xl border border-slate-200 p-4 space-y-4">
                        <legend class="px-2 font-bold text-[#02143C]">End customer</legend>
                        <p class="text-sm text-slate-600">Select an existing customer or create a new one. This customer does not need a login account.</p>
                        <div class="flex flex-wrap gap-5 text-sm">
                            <label class="flex items-center gap-2"><input type="radio" name="customer_mode" value="existing" @checked(old('customer_mode', 'new') === 'existing')> Existing customer</label>
                            <label class="flex items-center gap-2"><input type="radio" name="customer_mode" value="new" @checked(old('customer_mode', 'new') !== 'existing')> New customer</label>
                        </div>
                        <div id="existing_customer_fields" class="space-y-3" hidden>
                            <label for="customer_search" class="block text-sm font-medium">Search by name, email or phone</label>
                            <input id="customer_search" type="search" maxlength="100" autocomplete="off" placeholder="Search customers" class="w-full rounded-xl border border-slate-300 p-3">
                            <label for="customer_id" class="block text-sm font-medium">Customer</label>
                            <select id="customer_id" name="customer_id" data-old-value="{{ old('customer_id') }}" class="w-full rounded-xl border border-slate-300 p-3">
                                <option value="">Select a customer</option>
                            </select>
                            <p id="customer_status" class="text-xs text-slate-500" role="status" aria-live="polite"></p>
                        </div>
                        <div id="new_customer_fields" class="grid gap-3 sm:grid-cols-2" hidden>
                            <div><label for="customer_first_name" class="block text-sm font-medium">First name</label>
                                <input id="customer_first_name" name="customer[first_name]" maxlength="100" value="{{ old('customer.first_name') }}" autocomplete="given-name" class="mt-1 w-full rounded-xl border border-slate-300 p-3"></div>
                            <div><label for="customer_last_name" class="block text-sm font-medium">Last name</label>
                                <input id="customer_last_name" name="customer[last_name]" maxlength="100" value="{{ old('customer.last_name') }}" autocomplete="family-name" class="mt-1 w-full rounded-xl border border-slate-300 p-3"></div>
                            <div><label for="customer_email" class="block text-sm font-medium">Email</label>
                                <input id="customer_email" type="email" name="customer[email]" maxlength="254" value="{{ old('customer.email') }}" autocomplete="email" class="mt-1 w-full rounded-xl border border-slate-300 p-3"></div>
                            <div><label for="customer_phone" class="block text-sm font-medium">Phone</label>
                                <input id="customer_phone" type="tel" name="customer[phone]" maxlength="35" value="{{ old('customer.phone') }}" autocomplete="tel" class="mt-1 w-full rounded-xl border border-slate-300 p-3"></div>
                        </div>
                    </fieldset>

                    {{-- Occupancy uses adults + children ages, not occupancy.guests. --}}
                    @foreach (($roomOccupancies ?? []) as $roomIndex => $occupancy)
                        @php
                            $adultCount = max(0, (int) ($occupancy['adults'] ?? 0));
                            $childAges = is_array($occupancy['children'] ?? null) ? $occupancy['children'] : [];
                            $guestTypes = array_merge(array_fill(0, $adultCount, 'adult'), array_fill(0, count($childAges), 'child'));
                        @endphp
                        <fieldset class="rounded-xl border border-slate-200 p-4">
                            <legend class="px-2 font-bold text-[#02143C]">Room {{ $roomIndex + 1 }} — Guest details</legend>
                            @foreach ($guestTypes as $guestIndex => $guestType)
                                <div class="mt-4">
                                    <p class="mb-2 text-xs font-semibold uppercase text-slate-500">{{ ucfirst($guestType) }} {{ $guestType === 'adult' ? $guestIndex + 1 : $guestIndex - $adultCount + 1 }}</p>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label for="guest_{{ $roomIndex }}_{{ $guestIndex }}_first" class="block text-xs font-medium text-slate-700">First name</label>
                                            <input id="guest_{{ $roomIndex }}_{{ $guestIndex }}_first" type="text"
                                                name="rooms[{{ $roomIndex }}][guests][{{ $guestIndex }}][first_name]"
                                                value="{{ old("rooms.$roomIndex.guests.$guestIndex.first_name") }}"
                                                maxlength="50" required autocomplete="off"
                                                class="mt-1 w-full rounded-xl border border-slate-300 p-3">
                                        </div>
                                        <div>
                                            <label for="guest_{{ $roomIndex }}_{{ $guestIndex }}_last" class="block text-xs font-medium text-slate-700">Last name</label>
                                            <input id="guest_{{ $roomIndex }}_{{ $guestIndex }}_last" type="text"
                                                name="rooms[{{ $roomIndex }}][guests][{{ $guestIndex }}][last_name]"
                                                value="{{ old("rooms.$roomIndex.guests.$guestIndex.last_name") }}"
                                                maxlength="50" required autocomplete="off"
                                                class="mt-1 w-full rounded-xl border border-slate-300 p-3">
                                        </div>
                                        <div>
                                            <label for="guest_{{ $roomIndex }}_{{ $guestIndex }}_gender" class="block text-xs font-medium text-slate-700">Gender <span class="text-slate-400">(required by some hotels)</span></label>
                                            @php $oldGender = old("rooms.$roomIndex.guests.$guestIndex.gender"); @endphp
                                            <select id="guest_{{ $roomIndex }}_{{ $guestIndex }}_gender"
                                                name="rooms[{{ $roomIndex }}][guests][{{ $guestIndex }}][gender]"
                                                class="mt-1 w-full rounded-xl border border-slate-300 p-3">
                                                <option value="">Not specified</option>
                                                <option value="male" @selected($oldGender === 'male')>Male</option>
                                                <option value="female" @selected($oldGender === 'female')>Female</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </fieldset>
                    @endforeach

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="contact_email" class="block text-sm font-semibold text-slate-800">Contact email</label>
                            <input id="contact_email" type="email" name="contact_email" value="{{ old('contact_email') }}" required
                                class="mt-2 w-full rounded-xl border border-slate-300 p-3">
                        </div>
                        <div>
                            <label for="contact_phone" class="block text-sm font-semibold text-slate-800">Contact phone</label>
                            <input id="contact_phone" type="tel" name="contact_phone" value="{{ old('contact_phone') }}" required
                                class="mt-2 w-full rounded-xl border border-slate-300 p-3">
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="font-semibold text-[#02143C]">Payment method</p>
                        <p class="mt-1 text-sm text-slate-700">B2B company: wallet (USD). Direct customer: separate payment workflow required.</p>
                        <p class="mt-2 text-xs text-slate-500">Funds are reserved before supplier booking and captured only after confirmed supplier success.</p>
                    </div>

                    <label class="flex items-start gap-3 text-sm text-slate-700">
                        <input type="checkbox" name="accept_terms" value="1" required @checked(old('accept_terms')) class="mt-1">
                        <span>I have reviewed the final price and cancellation conditions.</span>
                    </label>

                    <button type="submit"
                        class="rounded-xl bg-[#02143C] px-6 py-3 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">
                        Save Draft Booking
                    </button>
                    <p class="mt-3 text-xs text-slate-600">Creates a draft only. No payment or supplier reservation is made.</p>
                </form>
            @else
                <p class="mt-4 text-sm text-amber-800">The booking token is unavailable. Please restart the room review.</p>
            @endif
        </div>
    </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const company = document.getElementById('company_id');
    const search = document.getElementById('customer_search');
    const select = document.getElementById('customer_id');
    const status = document.getElementById('customer_status');
    const existing = document.getElementById('existing_customer_fields');
    const fresh = document.getElementById('new_customer_fields');
    const radios = [...document.querySelectorAll('input[name="customer_mode"]')];
    if (!company || !search || !select || !status || !existing || !fresh) return;

    function syncMode() {
        const isNew = document.querySelector('input[name="customer_mode"]:checked')?.value === 'new';
        existing.hidden = isNew;
        fresh.hidden = !isNew;
        select.disabled = isNew;
        select.required = !isNew;
        fresh.querySelectorAll('input').forEach(input => {
            input.disabled = !isNew;
            input.required = isNew && ['customer[first_name]', 'customer[last_name]'].includes(input.name);
        });
    }
    radios.forEach(radio => radio.addEventListener('change', syncMode));
    syncMode();

    let sequence = 0;
    let debounce;
    async function loadCustomers() {
        const current = ++sequence;
        const previousSelection = select.value || select.dataset.oldValue || '';
        select.replaceChildren(new Option('Select a customer', ''));
        select.dataset.oldValue = '';
        status.textContent = 'Loading customers…';
        const url = new URL(@json(route('admin.customers.index')), window.location.origin);
        if (company.value) url.searchParams.set('company_id', company.value);
        if (search.value.trim()) url.searchParams.set('q', search.value.trim());
        try {
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Customer lookup unavailable');
            const payload = await response.json();
            if (current !== sequence) return;
            for (const item of payload.data || []) {
                select.add(new Option([item.first_name, item.last_name, item.email || item.phone].filter(Boolean).join(' — '), item.id));
            }
            if ([...select.options].some(option => option.value === previousSelection)) select.value = previousSelection;
            status.textContent = `${(payload.data || []).length} customers found`;
        } catch (error) {
            if (current === sequence) status.textContent = 'Could not load customers. Try again or select New customer.';
        }
    }
    company.addEventListener('change', () => {
        select.value = '';
        select.dataset.oldValue = '';
        search.value = '';
        loadCustomers();
    });
    search.addEventListener('input', () => { clearTimeout(debounce); debounce = setTimeout(loadCustomers, 250); });
    loadCustomers();
});
</script>
@endsection

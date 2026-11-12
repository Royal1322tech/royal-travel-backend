@extends('admin.layouts.app')

@section('content')
@php
    $mealLabels = [
        'nomeal' => 'Room Only', 'no_meal' => 'Room Only', 'room only' => 'Room Only',
        'breakfast' => 'Breakfast Included', 'breakfast included' => 'Breakfast Included',
        'half-board' => 'Half Board', 'half_board' => 'Half Board', 'halfboard' => 'Half Board',
        'full-board' => 'Full Board', 'full_board' => 'Full Board', 'fullboard' => 'Full Board',
        'all-inclusive' => 'All Inclusive', 'all_inclusive' => 'All Inclusive', 'allinclusive' => 'All Inclusive',
        'ultra-all-inclusive' => 'Ultra All Inclusive', 'ultra_all_inclusive' => 'Ultra All Inclusive',
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
            These are search-time rate options, not confirmed offers. Room recheck is available. Final prices, cancellation and payment terms must be reviewed before booking. Booking and payment remain disabled.
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
                    <div class="w-full sm:w-auto">
                        <form class="js-prebook-form" method="POST" action="{{ route('admin.hotels.rooms.prebook', ['token' => $token]) }}">
                            @csrf
                            <input type="hidden" name="rate_index" value="{{ $room['rate_index'] }}">
                            <button type="submit" class="js-prebook-button rounded-xl bg-[#02143C] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#102B62] disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-[#B7731E] focus:ring-offset-2">Select &amp; Recheck</button>
                        </form>
                        <div class="js-prebook-feedback mt-3 hidden max-w-xs rounded-lg border p-3 text-sm" role="status" aria-live="polite"></div>
                        <div class="js-review-action mt-3 hidden"></div>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-500">No room rates available in this search snapshot. Please run a new hotel search.</div>
        @endforelse
    </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.js-prebook-form').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const button = form.querySelector('.js-prebook-button');
            const feedback = form.parentElement.querySelector('.js-prebook-feedback');
            const reviewAction = form.parentElement.querySelector('.js-review-action');
            reviewAction.replaceChildren();
            reviewAction.classList.add('hidden');
            button.disabled = true;
            button.textContent = 'Rechecking…';
            feedback.className = 'js-prebook-feedback mt-3 max-w-xs rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700';
            feedback.textContent = 'Checking room availability…';
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
                    },
                    body: JSON.stringify({ rate_index: Number(form.querySelector('input[name="rate_index"]').value) })
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const messages = {
                        403: 'You are not authorized to recheck this room.',
                        409: 'This room rate is no longer valid. Search again.',
                        410: 'Search expired. Please search again.',
                        419: 'Session expired. Refresh the page and try again.',
                        422: 'Invalid room selection.',
                        503: 'Supplier recheck is unavailable. Please try again.'
                    };
                    throw new Error(messages[response.status] || 'Recheck failed. Please try again.');
                }
                if (data.status !== 'rechecked' || data.booking_enabled !== false) {
                    throw new Error('Unexpected recheck response.');
                }
                feedback.className = data.price_changed
                    ? 'js-prebook-feedback mt-3 max-w-xs rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900'
                    : 'js-prebook-feedback mt-3 max-w-xs rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700';
                feedback.textContent = (data.price_changed ? 'Price changed — review required. ' : 'Availability rechecked. ') + 'No reservation or payment has been made.';

                if (typeof data.review_url === 'string' && data.review_url.trim() !== '') {
                    const reviewUrl = new URL(data.review_url, window.location.origin);
                    if (reviewUrl.origin !== window.location.origin || !reviewUrl.pathname.startsWith('/admin/hotels/rooms/review/')) {
                        throw new Error('Invalid booking review link.');
                    }
                    const link = document.createElement('a');
                    link.href = reviewUrl.href;
                    link.textContent = 'Continue to Booking Review';
                    link.className = 'inline-flex rounded-xl bg-[#02143C] px-5 py-3 text-sm font-semibold text-white hover:bg-[#102B62] focus:outline-none focus:ring-2 focus:ring-[#B7731E]';
                    reviewAction.appendChild(link);
                    reviewAction.classList.remove('hidden');
                } else {
                    feedback.textContent += ' Booking Review link is not available yet; verify the Controller returns review_url.';
                }
            } catch (error) {
                reviewAction.replaceChildren();
                reviewAction.classList.add('hidden');
                feedback.className = 'js-prebook-feedback mt-3 max-w-xs rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700';
                feedback.textContent = error.message || 'Recheck failed.';
            } finally {
                button.disabled = false;
                button.textContent = 'Select & Recheck';
            }
        });
    });
});
</script>
@endsection

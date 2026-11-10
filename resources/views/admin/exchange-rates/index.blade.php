@extends('admin.layouts.app')

@section('content')
<div class="mx-auto max-w-6xl space-y-6 px-4 py-8">
    <div>
        <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Finance / Exchange Rates</p>
        <h1 class="mt-2 text-3xl font-bold text-[#02143C]">Exchange Rate Management</h1>
        <p class="mt-2 text-sm text-slate-500">Manage the additional conversion markup applied to USD reference rates. Supplier settlement remains in USD.</p>
    </div>

    @if(session('success'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <strong>Unable to save:</strong>
            <ul class="mt-2 list-disc ps-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach(['EGP' => 'Egyptian Pound', 'SAR' => 'Saudi Riyal'] as $currency => $label)
            @php
                $rate = $rates[$currency];
                $adjustment = $adjustments[$currency];
                $type = $adjustment?->adjustment_type ?? 'fixed';
                $value = (string) ($adjustment?->adjustment_value ?? '0');
                $enabled = (bool) ($adjustment?->is_enabled ?? false);
                $reference = $rate ? (float) $rate->rate : null;
                $effective = $reference === null ? null : ($enabled ? ($type === 'percentage' ? $reference * (1 + (float) $value / 100) : $reference + (float) $value) : $reference);
            @endphp
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" data-fx-card>
                <div class="border-b border-slate-100 p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-bold text-[#02143C]">USD → {{ $currency }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $label }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $rate ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">
                            {{ $rate ? 'Reference rate active' : 'Reference rate unavailable' }}
                        </span>
                    </div>
                    <div class="mt-5 rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Reference rate · 1 USD</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $rate ? number_format($reference, 6, '.', ',') . ' ' . $currency : 'Not available' }}</p>
                        @if($rate)
                            <p class="mt-1 text-xs text-slate-500">Effective: {{ $rate->effective_at }} · Expires: {{ $rate->expires_at }}</p>
                        @else
                            <p class="mt-1 text-xs text-amber-800">Sync reference rates before displaying verified conversions.</p>
                        @endif
                    </div>
                </div>

                <form action="{{ route('admin.exchange-rates.save') }}" method="POST" class="space-y-5 p-6" data-fx-form>
                    @csrf
                    <input type="hidden" name="quote_currency" value="{{ $currency }}">
                    <div>
                        <label for="type-{{ $currency }}" class="mb-2 block text-sm font-medium text-slate-700">Markup type</label>
                        <select id="type-{{ $currency }}" name="adjustment_type" data-type class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-[#02143C] focus:outline-none" @disabled(!$canManage)>
                            <option value="fixed" @selected($type === 'fixed')>Fixed amount ({{ $currency }} per USD)</option>
                            <option value="percentage" @selected($type === 'percentage')>Percentage (%)</option>
                        </select>
                    </div>
                    <div>
                        <label for="value-{{ $currency }}" class="mb-2 block text-sm font-medium text-slate-700">Markup value</label>
                        <input id="value-{{ $currency }}" name="adjustment_value" type="number" min="0" step="0.000001" max="1000000" required value="{{ $value }}" data-value class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-[#02143C] focus:outline-none" @disabled(!$canManage)>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="hidden" name="is_enabled" value="0" @disabled(!$canManage)>
                        <input id="enabled-{{ $currency }}" type="checkbox" name="is_enabled" value="1" data-enabled class="h-4 w-4 accent-[#02143C]" @checked($enabled) @disabled(!$canManage)>
                        <label for="enabled-{{ $currency }}" class="text-sm font-medium text-slate-700">Enable markup</label>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4" data-reference="{{ $reference ?? '' }}" data-currency="{{ $currency }}">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Effective conversion rate · preview</p>
                        <p class="mt-1 text-xl font-bold text-[#02143C]" data-effective>{{ $effective !== null ? number_format($effective, 6, '.', ',') . ' ' . $currency : 'Unavailable' }}</p>
                        <p class="mt-1 text-xs text-slate-500" data-hundred>{{ $effective !== null ? '100 USD ≈ ' . number_format($effective * 100, 2, '.', ',') . ' ' . $currency : 'Waiting for reference rate' }}</p>
                        <p class="mt-2 text-xs text-slate-400">Preview is illustrative; server-side conversion uses decimal arithmetic.</p>
                    </div>
                    @if($canManage)
                        <button type="submit" class="w-full rounded-lg bg-[#02143C] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#102e67]">Save {{ $currency }} settings</button>
                    @else
                        <p class="text-sm text-slate-500">You have read-only access to exchange-rate settings.</p>
                    @endif
                </form>
            </section>
        @endforeach
    </div>
</div>
<script>
(function () {
    document.querySelectorAll('[data-fx-card]').forEach(function (card) {
        const form = card.querySelector('[data-fx-form]');
        const box = card.querySelector('[data-reference]');
        const type = form.querySelector('[data-type]');
        const value = form.querySelector('[data-value]');
        const enabled = form.querySelector('[data-enabled]');
        const effectiveLabel = box.querySelector('[data-effective]');
        const hundredLabel = box.querySelector('[data-hundred]');
        const reference = Number(box.dataset.reference);
        const hasReference = box.dataset.reference !== '' && Number.isFinite(reference) && reference > 0;
        const currency = box.dataset.currency;
        function refresh() {
            const amount = Number(value.value);
            const valid = value.value !== '' && Number.isFinite(amount) && amount >= 0 && amount <= (type.value === 'percentage' ? 100 : 1000000);
            if (!hasReference || !valid) {
                effectiveLabel.textContent = !hasReference ? 'Unavailable' : 'Invalid markup';
                hundredLabel.textContent = !hasReference ? 'Waiting for reference rate' : 'Check markup value';
                return;
            }
            const result = !enabled.checked ? reference : type.value === 'percentage' ? reference * (1 + amount / 100) : reference + amount;
            effectiveLabel.textContent = result.toLocaleString('en-US', {minimumFractionDigits: 6, maximumFractionDigits: 6}) + ' ' + currency;
            hundredLabel.textContent = '100 USD ≈ ' + (result * 100).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + currency;
        }
        [type, value, enabled].forEach(function (el) { el.addEventListener('input', refresh); el.addEventListener('change', refresh); });
        refresh();
    });
})();
</script>
@endsection

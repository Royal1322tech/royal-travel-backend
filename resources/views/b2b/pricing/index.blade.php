@extends('b2b.layouts.app')
@section('title', __('pricing.title'))
@section('content')
<div class="mx-auto max-w-5xl space-y-6 py-8" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">B2B / Hotels</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ __('pricing.title') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('pricing.description') }}</p>
        </div>
        <span class="rounded-full bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-700">USD</span>
    </header>

    @if(session('success'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('pricing_conflict'))
        <div role="alert" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ session('pricing_conflict') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @unless($hasRoyalPrice)
        <div role="alert" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ __('pricing.royal_missing') }}</div>
    @endunless

    <div class="grid gap-5 lg:grid-cols-5">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('pricing.settings') }}</h2>
                <span class="text-xs text-slate-500">{{ __('pricing.version') }}: {{ $companyRule['version'] ?? 0 }}</span>
            </div>
            <form method="POST" action="{{ route('b2b.pricing.save') }}" class="mt-6 space-y-5" id="agency-pricing-form">
                @csrf
                <input type="hidden" name="expected_version" value="{{ $companyRule['version'] ?? 0 }}">
                <div>
                    <label for="percentage" class="block text-sm font-medium text-slate-700">{{ __('pricing.percentage') }}</label>
                    <input id="percentage" name="percentage" type="number" inputmode="decimal" min="0" max="100" step="0.000001" required
                        value="{{ old('percentage', $companyRule['percentage'] ?? '0') }}"
                        class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 focus:border-slate-600 focus:outline-none" dir="ltr">
                    @error('percentage')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="fixed" class="block text-sm font-medium text-slate-700">{{ __('pricing.fixed') }}</label>
                    <input id="fixed" name="fixed" type="number" inputmode="decimal" min="0" max="1000000" step="0.01" required
                        value="{{ old('fixed', $companyRule['fixed'] ?? '0') }}"
                        class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 focus:border-slate-600 focus:outline-none" dir="ltr">
                    @error('fixed')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <button type="submit" @disabled(!$hasRoyalPrice)
                    class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-40">
                    {{ __('pricing.save') }}
                </button>
            </form>
        </section>
        <aside class="rounded-2xl border border-slate-200 bg-slate-50 p-6 lg:col-span-2">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('pricing.preview_title') }}</h2>
            <p class="mt-2 text-sm text-slate-500">{{ __('pricing.preview_note') }}</p>
            <div class="mt-6 space-y-4" dir="ltr">
                <div class="flex justify-between gap-3 text-sm"><span class="text-slate-500">{{ __('pricing.example_base') }}</span><strong>103.00 USD</strong></div>
                <div class="flex justify-between gap-3 text-sm"><span class="text-slate-500">{{ __('pricing.markup_amount') }}</span><strong id="preview-markup">—</strong></div>
                <div class="border-t border-slate-200 pt-4 flex justify-between gap-3"><strong>{{ __('pricing.customer_price') }}</strong><strong id="preview-total" class="text-xl">—</strong></div>
            </div>
            <p class="mt-6 text-xs leading-5 text-slate-500">{{ __('pricing.example_note') }}</p>
            <p class="mt-2 text-xs leading-5 text-slate-500">{{ __('pricing.no_supplier_data') }}</p>
        </aside>
    </div>
</div>
<script>
(() => {
    const pct = document.getElementById('percentage');
    const fixed = document.getElementById('fixed');
    const markup = document.getElementById('preview-markup');
    const total = document.getElementById('preview-total');
    if (!pct || !fixed || !markup || !total) return;
    const update = () => {
        const p = Number(pct.value), f = Number(fixed.value);
        if (!Number.isFinite(p) || !Number.isFinite(f) || p < 0 || p > 100 || f < 0 || f > 1000000) {
            markup.textContent = total.textContent = '—'; return;
        }
        // Illustration only; authoritative amounts are always computed server-side.
        const extra = Math.round((103 * p / 100 + f) * 100) / 100;
        markup.textContent = extra.toFixed(2) + ' USD';
        total.textContent = (103 + extra).toFixed(2) + ' USD';
    };
    pct.addEventListener('input', update);
    fixed.addEventListener('input', update);
    update();
})();
</script>
@endsection

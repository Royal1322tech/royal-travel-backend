@extends('admin.layouts.app')

@section('title', 'Pricing & Commissions')

@section('content')
@php
$directPercent = old('level') === 'royal_direct' ? old('percentage', '0') : ($direct['percentage'] ?? '0');
$directFixed = number_format((float) (old('level') === 'royal_direct' ? old('fixed', '0') : ($direct['fixed'] ?? '0')), 2, '.', '');
$companyCount = method_exists($companies, 'total') ? $companies->total() : $companies->count();
@endphp

<div class="mx-auto max-w-[1440px] space-y-7 pb-10">
    {{-- Page heading --}}
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <div class="mb-3 flex items-center gap-2 text-xs font-semibold text-slate-400">
                <span>Hotel Management</span>
                <span class="text-slate-300">/</span>
                <span class="text-[#B7731E]">Pricing</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-[#02143C] sm:text-[30px]">Pricing & Commissions</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                Manage Royal's direct selling markup and the wholesale markup assigned to each B2B company.
            </p>
        </div>
        <div class="inline-flex w-fit items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 shadow-sm">
            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
            Fixed markup currency: USD
        </div>
    </header>

    @if (session('success'))
    <div role="status" class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        <span aria-hidden="true" class="font-bold">✓</span>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    @if ($errors->any())
    <div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <p class="font-semibold">Please review the following:</p>
        <ul class="mt-2 list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Overview --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Direct channel</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#EEF1F7] text-lg text-[#02143C]">↗</span>
            </div>
            <p class="mt-3 text-xl font-bold text-[#02143C]">B2C Pricing</p>
            <p class="mt-1 text-xs text-slate-500">Royal markup for direct customers</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">B2B accounts</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#F9F1E8] text-lg text-[#B7731E]">▦</span>
            </div>
            <p class="mt-3 text-2xl font-bold tabular-nums text-[#02143C]">{{ number_format($companyCount) }}</p>
            <p class="mt-1 text-xs text-slate-500">Companies available for pricing setup</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Pricing policy</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-lg text-slate-600">◎</span>
            </div>
            <p class="mt-3 text-xl font-bold text-[#02143C]">Separate Markups</p>
            <p class="mt-1 text-xs text-slate-500">B2C settings never apply to B2B wholesale</p>
        </div>
    </div>

    {{-- Royal direct --}}
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="royal-direct-title">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#02143C] text-lg font-bold text-white">%</div>
                <div>
                    <h2 id="royal-direct-title" class="text-base font-bold text-[#02143C]">Royal Direct Pricing</h2>
                    <p class="mt-0.5 text-xs text-slate-500">B2C customers · Royal's own markup</p>
                </div>
            </div>
            <span class="inline-flex w-fit items-center rounded-full px-3 py-1 text-xs font-semibold {{ $direct ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                {{ $direct ? 'Active · Version '.$direct['version'] : 'Not configured' }}
            </span>
        </div>

        <form method="POST" action="{{ route('admin.pricing.save') }}" class="px-5 py-6 sm:px-7">
            @csrf
            <input type="hidden" name="level" value="royal_direct">

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start">
                <div>
                    <p class="mb-4 text-sm font-semibold text-slate-700">Markup configuration</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="direct-percentage" class="mb-2 block text-xs font-semibold text-slate-600">Percentage markup</label>
                            <div class="relative">
                                <input id="direct-percentage" name="percentage" type="number" min="0" max="100" step="0.000001" required value="{{ $directPercent }}" class="w-full rounded-xl border border-slate-200 bg-white py-3 pe-12 ps-4 text-sm font-semibold text-[#02143C] outline-none transition focus:border-[#B7731E] focus:ring-2 focus:ring-[#B7731E]/10">
                                <span class="pointer-events-none absolute inset-y-0 end-4 flex items-center text-sm font-semibold text-slate-400">%</span>
                            </div>
                            <p class="mt-2 text-xs text-slate-400">Calculated from supplier cost</p>
                        </div>
                        <div>
                            <label for="direct-fixed" class="mb-2 block text-xs font-semibold text-slate-600">Fixed markup</label>
                            <div class="relative">
                                <input id="direct-fixed" name="fixed" type="number" min="0" max="1000000" step="0.01" required value="{{ $directFixed }}" class="w-full rounded-xl border border-slate-200 bg-white py-3 pe-16 ps-4 text-sm font-semibold text-[#02143C] outline-none transition focus:border-[#B7731E] focus:ring-2 focus:ring-[#B7731E]/10">
                                <span class="pointer-events-none absolute inset-y-0 end-4 flex items-center text-xs font-bold text-slate-400">USD</span>
                            </div>
                            <p class="mt-2 text-xs text-slate-400">Added after the percentage</p>
                        </div>
                    </div>
                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#02143C] px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#092657] focus:outline-none focus:ring-2 focus:ring-[#B7731E] focus:ring-offset-2">
                            Save Direct Pricing
                        </button>
                        <span class="text-xs text-slate-400">Saving creates a new pricing version</span>
                    </div>
                </div>

                <div class="rounded-2xl border border-[#E8E0D5] bg-[#FAF8F5] p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-[#986223]">Pricing example</p>
                    <p class="mt-1 text-xs text-slate-500">Illustrative supplier cost of $100</p>
                    <div class="mt-5 flex items-center justify-between text-sm">
                        <span class="text-slate-500">Supplier cost</span>
                        <span class="font-semibold tabular-nums text-[#02143C]">$100.00</span>
                    </div>
                    <div class="mt-3 flex items-center justify-between text-sm">
                        <span class="text-slate-500">Royal markup</span>
                        <span id="direct-example-markup" class="font-semibold tabular-nums text-[#02143C]">—</span>
                    </div>
                    <div class="my-4 border-t border-[#E8E0D5]"></div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-bold text-[#02143C]">Example selling price</span>
                        <span id="direct-example-total" class="text-xl font-bold tabular-nums text-[#02143C]">—</span>
                    </div>
                    <p class="mt-3 text-[11px] leading-5 text-slate-500">Preview only. Taxes, FX and supplier recheck are not included.</p>
                </div>
            </div>
        </form>
    </section>

    {{-- Royal B2B company wholesale markup --}}
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="royal-b2b-title">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7">
            <div>
                <h2 id="royal-b2b-title" class="text-base font-bold text-[#02143C]">Royal Markup by B2B Company</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">Each company receives its own Royal wholesale price. Company-side customer markup is configured separately.</p>
            </div>
            <span class="inline-flex w-fit items-center rounded-full bg-[#EEF1F7] px-3 py-1.5 text-xs font-semibold text-[#02143C]">{{ number_format($companyCount) }} companies</span>
        </div>

        <div class="overflow-x-auto px-5 pb-2 pt-2 sm:px-7">
            <table class="w-full min-w-[780px] border-separate border-spacing-0 text-left text-sm">
                <thead>
                    <tr class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        <th scope="col" class="w-[28%] border-b border-slate-100 px-3 py-4">Company</th>
                        <th scope="col" class="w-[21%] border-b border-slate-100 px-3 py-4">Royal markup %</th>
                        <th scope="col" class="w-[21%] border-b border-slate-100 px-3 py-4">Fixed markup USD</th>
                        <th scope="col" class="w-[12%] border-b border-slate-100 px-3 py-4">Version</th>
                        <th scope="col" class="w-[18%] border-b border-slate-100 px-3 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($companies as $company)
                    @php
                    $companyFormId = 'company-pricing-'. $company->id;
                    $companyOld = old('level') === 'royal_b2b' && old('company_id') === (string) $company->id;
                    $companyPercentage = $companyOld ? old('percentage', '0') : ($company->royal_rule['percentage'] ?? '0');
                   $companyFixed = number_format((float) ($companyOld ? old('fixed', '0') : ($company->royal_rule['fixed'] ?? '0')), 2, '.', '');
                    @endphp
                    <tr class="group">
                        <td class="border-b border-slate-100 px-3 py-4 align-middle">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#EEF1F7] text-sm font-bold text-[#02143C]">
                                    {{ strtoupper(mb_substr($company->display_name ?: $company->legal_name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-[#02143C]">{{ $company->display_name ?: $company->legal_name }}</p>
                                    <p class="mt-1 text-xs capitalize text-slate-400">{{ $company->status }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="border-b border-slate-100 px-3 py-4 align-middle">
                            <form id="{{ $companyFormId }}" method="POST" action="{{ route('admin.pricing.save') }}">
                                @csrf
                                <input type="hidden" name="level" value="royal_b2b">
                                <input type="hidden" name="company_id" value="{{ $company->id }}">
                            </form>
                            <div class="relative">
                                <input form="{{ $companyFormId }}" aria-label="Royal percentage for {{ $company->legal_name }}" type="number" name="percentage" min="0" max="100" step="0.000001" required value="{{ $companyPercentage }}" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pe-9 ps-3 text-sm font-semibold text-[#02143C] outline-none focus:border-[#B7731E] focus:ring-2 focus:ring-[#B7731E]/10">
                                <span class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-xs text-slate-400">%</span>
                            </div>
                        </td>
                        <td class="border-b border-slate-100 px-3 py-4 align-middle">
                            <div class="relative">
                                <input form="{{ $companyFormId }}" aria-label="Royal fixed markup for {{ $company->legal_name }}" type="number" name="fixed" min="0" max="1000000" step="0.01" required value="{{ $companyFixed }}" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pe-14 ps-3 text-sm font-semibold text-[#02143C] outline-none focus:border-[#B7731E] focus:ring-2 focus:ring-[#B7731E]/10">
                                <span class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-[11px] font-semibold text-slate-400">USD</span>
                            </div>
                        </td>
                        <td class="border-b border-slate-100 px-3 py-4 align-middle">
                            @if ($company->royal_rule)
                            <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">v{{ $company->royal_rule['version'] }}</span>
                            @else
                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500">New</span>
                            @endif
                        </td>
                        <td class="border-b border-slate-100 px-3 py-4 text-right align-middle">
                            <button form="{{ $companyFormId }}" type="submit" class="inline-flex items-center justify-center rounded-xl border border-[#02143C] px-5 py-2.5 text-xs font-bold text-[#02143C] transition hover:bg-[#02143C] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#B7731E] focus:ring-offset-2">
                                Save Changes
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-sm text-slate-500">No companies available for pricing setup.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (method_exists($companies, 'hasPages') && $companies->hasPages())
        <div class="border-t border-slate-100 px-5 py-4 sm:px-7">{{ $companies->links() }}</div>
        @endif
    </section>

    <div class="flex items-start gap-3 rounded-xl border border-slate-200 bg-[#F8FAFC] px-5 py-4 text-xs leading-6 text-slate-500">
        <span aria-hidden="true" class="mt-0.5 text-base text-[#B7731E]">ⓘ</span>
        <p><strong class="text-slate-700">Pricing separation:</strong> Royal Direct applies only to B2C. Royal B2B markup is set per company, and the company's own end-customer markup is a separate layer. Supplier costs and Royal margins must never be exposed in the B2B portal.</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function() {
        const percentInput = document.getElementById('direct-percentage');
        const fixedInput = document.getElementById('direct-fixed');
        const markupOutput = document.getElementById('direct-example-markup');
        const totalOutput = document.getElementById('direct-example-total');
        if (!percentInput || !fixedInput || !markupOutput || !totalOutput) return;

        const formatUSD = (amount) => new Intl.NumberFormat('en-US', {
            style: 'currency'
            , currency: 'USD'
            , minimumFractionDigits: 2
            , maximumFractionDigits: 2
        }).format(amount);

        function updateExample() {
            const percentage = Number(percentInput.value);
            const fixed = Number(fixedInput.value);
            if (!Number.isFinite(percentage) || !Number.isFinite(fixed) || percentage < 0 || fixed < 0 || percentage > 100 || fixed > 1000000) {
                markupOutput.textContent = '—';
                totalOutput.textContent = '—';
                return;
            }
            const markup = 100 * percentage / 100 + fixed;
            markupOutput.textContent = '+' + formatUSD(markup);
            totalOutput.textContent = formatUSD(100 + markup);
        }
        percentInput.addEventListener('input', updateExample);
        fixedInput.addEventListener('input', updateExample);
        updateExample();
    })();

</script>
@endpush

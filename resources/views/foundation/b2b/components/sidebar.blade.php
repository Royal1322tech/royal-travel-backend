<aside class="hidden w-64 shrink-0 border-e border-slate-100 bg-white lg:flex lg:flex-col">

    <div class="flex h-20 items-center gap-3 px-7">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#02143C] text-lg font-bold text-white">
            R
        </div>
        <div>
            <div class="text-base font-bold tracking-tight text-[#02143C]">ROYAL</div>
            <div class="text-[10px] font-semibold tracking-[0.2em] text-[#B7731E]">VACATION</div>
        </div>
    </div>

    <nav class="flex-1 space-y-1 px-4 py-7">
        <p class="mb-4 px-4 text-[11px] font-semibold uppercase tracking-widest text-slate-400">
            Main Menu
        </p>

@php
    $menuItems = [
        [
            'label' => 'Dashboard',
            'icon' => '▦',
            'url' => url('/ui-preview/b2b/dashboard'),
            'active' => request()->is('ui-preview/b2b/dashboard'),
        ],
        [
            'label' => 'Bookings',
            'icon' => '▤',
            'url' => url('/ui-preview/b2b/all-bookings'),
            'active' => request()->is('ui-preview/b2b/all-bookings'),
        ],
        [
            'label' => 'Hotel Offline Requests',
            'icon' => '▧',
            'url' => url('/ui-preview/b2b/bookings'),
            'active' => request()->is('ui-preview/b2b/bookings')
                || request()->is('ui-preview/b2b/bookings/*'),
        ],
        [
            'label' => 'Hotels',
            'icon' => '⌂',
            'url' => url('/ui-preview/b2b/hotels'),
            'active' => request()->is('ui-preview/b2b/hotels')
                || request()->is('ui-preview/b2b/hotels/*'),
        ],
        [
            'label' => 'My Pricing & Markup',
            'icon' => '％',
            'url' => Route::has('b2b.pricing.index')
                ? route('b2b.pricing.index')
                : null,
            'active' => request()->routeIs('b2b.pricing.*'),
        ],
        [
            'label' => 'Group Requests',
            'icon' => '♧',
            'url' => null,
            'active' => false,
        ],
    ];
@endphp


        @foreach ($menuItems as $item)
            @if ($item['url'])
                <a href="{{ $item['url'] }}"
                   @if ($item['active']) aria-current="page" @endif
                   class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium
                   {{ $item['active'] ? 'bg-[#F5EDE8] text-[#B7731E]' : 'text-slate-500 hover:bg-slate-50 hover:text-[#02143C]' }}">
                    <span class="w-5 text-center text-lg">{{ $item['icon'] }}</span>
                    <span>{{ $item['label'] }}</span>
                </a>
            @else
                <div class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-slate-400">
                    <span class="w-5 text-center text-lg">{{ $item['icon'] }}</span>
                    <span>{{ $item['label'] }}</span>
                </div>
            @endif
        @endforeach
    </nav>

    <div class="p-5">
        <div class="rounded-2xl bg-[#F5F0E8] p-5">
            <p class="text-sm font-bold text-[#02143C]">Need Assistance?</p>
            <p class="mt-2 text-xs leading-5 text-slate-500">
                Our support team is here to help you.
            </p>
            <div class="mt-4 rounded-lg bg-[#B7731E] px-4 py-2.5 text-center text-xs font-semibold text-white">
                Contact Support
            </div>
        </div>
    </div>

</aside>

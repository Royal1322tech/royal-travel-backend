@php
    /*
    |--------------------------------------------------------------------------
    | Admin Sidebar Menu
    |--------------------------------------------------------------------------
    */

    $menuGroups = [
        [
            'title' => 'OVERVIEW',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'icon' => '▦',
                    'path' => 'dashboard',
                ],
                [
                    'label' => 'Bookings',
                    'icon' => '▤',
                    'path' => 'bookings',
                ],
                [
                    'label' => 'Orders',
                    'icon' => '▣',
                    'path' => 'orders',
                ],
            ],
        ],
        [
            'title' => 'MANAGEMENT',
            'items' => [
                [
                    'label' => 'Companies',
                    'icon' => '▥',
                    'path' => 'companies',
                ],
                [
                    'label' => 'Customers',
                    'icon' => '♙',
                    'path' => 'customers',
                ],
                [
                    'label' => 'Suppliers',
                    'icon' => '◇',
                    'path' => 'suppliers',
                ],
                [
                    'label' => 'Hotel Search',
                    'icon' => '⌕',
                    'path' => 'hotels',
                ],
                [
                    'label' => 'Hotels & Inventory',
                    'icon' => '⌂',
                    'path' => 'inventory',
                ],
                [
                    'label' => 'Requests',
                    'icon' => '☷',
                    'path' => 'requests',
                ],
            ],
        ],
        [
            'title' => 'FINANCE',
            'items' => [
                [
                    'label' => 'Payments',
                    'icon' => '▤',
                    'path' => 'payments',
                ],
                [
                    'label' => 'Refunds',
                    'icon' => '↩',
                    'path' => 'refunds',
                ],
                [
                    'label' => 'Credit Management',
                    'icon' => '▦',
                    'path' => 'credit',
                ],
                [
                    'label' => 'Pricing & Commissions',
                    'icon' => '◇',
                    'path' => 'pricing',
                ],
            ],
        ],
        [
            'title' => 'SYSTEM',
            'items' => [
                [
                    'label' => 'Users & Roles',
                    'icon' => '♙',
                    'path' => 'users',
                ],
                [
                    'label' => 'Reports',
                    'icon' => '▥',
                    'path' => 'reports',
                ],
                [
                    'label' => 'Settings',
                    'icon' => '⚙',
                    'path' => 'settings',
                ],
            ],
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | Available Routes
    |--------------------------------------------------------------------------
    | Only implemented routes will be clickable.
    */

    $availableRoutes = [
        'dashboard' => 'admin.dashboard',
        'bookings' => 'admin.bookings.index',
        'companies' => 'admin.companies.index',
        'suppliers' => 'admin.suppliers.index',
        'hotels' => 'admin.hotels.index',
        'pricing' => 'admin.pricing.index',
    ];
@endphp

<aside
    class="sticky top-0 flex h-screen w-64 shrink-0 flex-col border-e border-slate-200 bg-white"
>

    {{-- Logo --}}
    <div class="flex h-20 items-center gap-3 border-b border-slate-100 px-6">

        <div
            class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#02143C] text-lg font-bold text-white"
        >
            R
        </div>

        <div>
            <div class="text-base font-bold tracking-tight text-[#02143C]">
                Royal Travel
            </div>

            <div class="text-xs font-medium text-slate-400">
                Admin Console
            </div>
        </div>

    </div>

    {{-- Navigation --}}
    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-6">

        @foreach ($menuGroups as $group)

            <div>

                {{-- Group Title --}}
                <div
                    class="mb-3 px-3 text-[10px] font-bold tracking-[0.16em] text-slate-400"
                >
                    {{ $group['title'] }}
                </div>

                <div class="space-y-1">

                    @foreach ($group['items'] as $item)

                        @php
                            $path = $item['path'];

                            $routeName = $availableRoutes[$path] ?? null;

                            /*
                             * Generate URL only if the route exists.
                             */
                            $url = $routeName
                                && \Illuminate\Support\Facades\Route::has($routeName)
                                    ? route($routeName)
                                    : null;

                            /*
                             * Active navigation state.
                             */
                            $active = match ($path) {
                                'dashboard' =>
                                    request()->is('admin/dashboard'),

                                'bookings' =>
                                    request()->is('admin/bookings')
                                    || request()->is('admin/bookings/*'),

                                'companies' =>
                                    request()->is('admin/companies')
                                    || request()->is('admin/companies/*'),

                                'suppliers' =>
                                    request()->is('admin/suppliers')
                                    || request()->is('admin/suppliers/*'),

                                'hotels' =>
                                    request()->is('admin/hotels')
                                    || request()->is('admin/hotels/*'),

                                default => false,
                            };
                        @endphp

                        @if ($url)

                            {{-- Clickable Navigation Link --}}
                            <a
                                href="{{ $url }}"
                                @if ($active)
                                    aria-current="page"
                                @endif

                                @class([
                                    'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',

                                    'bg-[#02143C] text-white shadow-sm' => $active,

                                    'text-slate-600 hover:bg-slate-100 hover:text-[#02143C]' => ! $active,
                                ])
                            >

                                {{-- Icon --}}
                                <span class="w-5 text-center text-lg">
                                    {{ $item['icon'] }}
                                </span>

                                {{-- Label --}}
                                <span class="flex-1">
                                    {{ $item['label'] }}
                                </span>

                                {{-- Active Indicator --}}
                                @if ($active)
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-[#B7731E]"
                                    ></span>
                                @endif

                            </a>

                        @else

                            {{-- Coming Soon --}}
                            <div
                                class="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-400"
                                title="Coming soon"
                                aria-disabled="true"
                            >

                                <span class="w-5 text-center text-lg">
                                    {{ $item['icon'] }}
                                </span>

                                <span class="flex-1">
                                    {{ $item['label'] }}
                                </span>

                            </div>

                        @endif

                    @endforeach

                </div>

            </div>

        @endforeach

    </nav>

    {{-- Admin Profile --}}
    <div class="border-t border-slate-100 p-4">

        <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-3">

            {{-- Avatar --}}
            <div
                class="flex h-10 w-10 items-center justify-center rounded-full bg-[#02143C] text-sm font-bold text-white"
            >
                {{ auth()->check()
                    ? strtoupper(substr(auth()->user()->name, 0, 2))
                    : 'AD' }}
            </div>

            {{-- User Information --}}
            <div class="min-w-0 flex-1">

                <div class="truncate text-sm font-semibold text-[#02143C]">
                    {{ auth()->user()?->name ?? 'Admin Preview' }}
                </div>

                <div class="truncate text-xs text-slate-400">
                    {{ auth()->user()?->email ?? 'UI Demo Mode' }}
                </div>

            </div>

        </div>

    </div>

</aside>

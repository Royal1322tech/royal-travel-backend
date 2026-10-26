@php
    $menuGroups = [
        [
            'title' => 'OVERVIEW',
            'items' => [
                ['label' => 'Dashboard', 'icon' => '▦', 'path' => 'dashboard'],
                ['label' => 'Bookings', 'icon' => '▤', 'path' => 'bookings'],
                ['label' => 'Orders', 'icon' => '▣', 'path' => 'orders'],
            ],
        ],
        [
            'title' => 'MANAGEMENT',
            'items' => [
                ['label' => 'Companies', 'icon' => '▥', 'path' => 'companies'],
                ['label' => 'Customers', 'icon' => '♙', 'path' => 'customers'],
                ['label' => 'Suppliers', 'icon' => '◇', 'path' => 'suppliers'],
                ['label' => 'Hotels & Inventory', 'icon' => '⌂', 'path' => 'inventory'],
                ['label' => 'Requests', 'icon' => '☷', 'path' => 'requests'],
            ],
        ],
        [
            'title' => 'FINANCE',
            'items' => [
                ['label' => 'Payments', 'icon' => '▤', 'path' => 'payments'],
                ['label' => 'Refunds', 'icon' => '↩', 'path' => 'refunds'],
                ['label' => 'Credit Management', 'icon' => '▦', 'path' => 'credit'],
                ['label' => 'Pricing & FX', 'icon' => '◇', 'path' => 'pricing'],
            ],
        ],
        [
            'title' => 'SYSTEM',
            'items' => [
                ['label' => 'Users & Roles', 'icon' => '♙', 'path' => 'users'],
                ['label' => 'Reports', 'icon' => '▥', 'path' => 'reports'],
                ['label' => 'Settings', 'icon' => '⚙', 'path' => 'settings'],
            ],
        ],
    ];
@endphp

<aside class="sticky top-0 flex h-screen w-64 shrink-0 flex-col border-e border-slate-200 bg-white">
    <div class="flex h-20 items-center gap-3 border-b border-slate-100 px-6">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#02143C] text-lg font-bold text-white">
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

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-6">
        @foreach ($menuGroups as $group)
            <div>
                <div class="mb-3 px-3 text-[10px] font-bold tracking-[0.16em] text-slate-400">
                    {{ $group['title'] }}
                </div>

                <div class="space-y-1">
                    @foreach ($group['items'] as $item)
                        @php
                            $active = request()->is('ui-preview/admin/' . $item['path'])
                                || request()->is('ui-preview/admin/' . $item['path'] . '/*');

                            $url = $item['path'] === 'dashboard'
                                ? url('/ui-preview/admin/dashboard')
                                : null;
                        @endphp

                        @if ($url)
                            <a href="{{ $url }}"
                               @class([
                                   'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                                   'bg-[#02143C] text-white' => $active,
                                   'text-slate-600 hover:bg-slate-100 hover:text-[#02143C]' => !$active,
                               ])>
                                <span class="w-5 text-center text-lg">{{ $item['icon'] }}</span>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @else
                            <div class="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-400"
                                 title="Coming soon">
                                <span class="w-5 text-center text-lg">{{ $item['icon'] }}</span>
                                <span>{{ $item['label'] }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    <div class="border-t border-slate-100 p-4">
        <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#02143C] text-sm font-bold text-white">
                AD
            </div>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-semibold text-[#02143C]">
                    Admin Preview
                </div>
                <div class="text-xs text-slate-400">
                    UI Demo Mode
                </div>
            </div>
        </div>
    </div>
</aside>

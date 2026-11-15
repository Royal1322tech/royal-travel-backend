@php
    $groups = [
        ['title' => 'MAIN', 'items' => [
            ['label' => 'Dashboard', 'icon' => '▦', 'route' => 'admin.dashboard', 'permission' => 'dashboard.view', 'match' => 'admin/dashboard'],
            ['label' => 'Bookings', 'icon' => '▤', 'route' => 'admin.bookings.index', 'permission' => 'bookings.view', 'match' => 'admin/bookings*'],
            ['label' => 'Hotel Search', 'icon' => '⌕', 'route' => 'admin.hotels.index', 'permission' => 'hotels.view', 'match' => 'admin/hotels*'],
            ['label' => 'Companies', 'icon' => '▥', 'route' => 'admin.companies.index', 'permission' => 'companies.view', 'match' => 'admin/companies*'],
            ['label' => 'Suppliers', 'icon' => '◇', 'route' => 'admin.suppliers.index', 'permission' => 'suppliers.view', 'match' => 'admin/suppliers*'],
        ]],
        ['title' => 'FINANCE', 'items' => [
            ['label' => 'Pricing & Commissions', 'icon' => '◈', 'route' => 'admin.pricing.index', 'permission' => 'pricing.royal.manage', 'match' => 'admin/pricing*'],
            ['label' => 'Exchange Rates', 'icon' => '⇄', 'route' => 'admin.exchange-rates.index', 'permission' => 'fx.view', 'match' => 'admin/exchange-rates*'],
        ]],
        ['title' => 'SYSTEM', 'items' => [
            ['label' => 'Users Management', 'icon' => '♙', 'route' => 'admin.users.index', 'permission' => 'users.view', 'match' => 'admin/users*'],
            ['label' => 'Roles & Permissions', 'icon' => '⚿', 'route' => 'admin.roles.index', 'permission' => 'roles.view', 'match' => 'admin/roles*'],
        ]],
    ];
    $permissionService = app(\App\Modules\Identity\Services\PermissionService::class);
    $currentUser = auth()->user();
@endphp
<aside class="royal-sidebar sticky top-0 flex h-screen w-[238px] flex-col border-e border-slate-100 bg-white">
    <a href="{{ \Illuminate\Support\Facades\Route::has('admin.dashboard') ? route('admin.dashboard') : '#' }}" class="royal-brand flex h-[78px] items-center gap-3 border-b border-slate-100 px-5">
        <div class="royal-brand-mark">R<span>✦</span></div>
        <div><div class="royal-brand-title">Royal <span>Travel</span></div><div class="royal-brand-subtitle">ADMIN WORKSPACE</div></div>
    </a>
    <nav class="flex-1 overflow-y-auto px-3 py-6" aria-label="Admin navigation">
        @foreach ($groups as $group)
            @php
                $visible = [];
                foreach ($group['items'] as $item) {
                    if (!\Illuminate\Support\Facades\Route::has($item['route'])) continue;
                    if (!$currentUser || !$permissionService->adminCan($currentUser, $item['permission'])) continue;
                    $visible[] = $item;
                }
            @endphp
            @if(count($visible))
                <div class="mb-7">
                    <div class="royal-nav-label px-3 pb-3">{{ $group['title'] }}</div>
                    <div class="space-y-1">
                        @foreach($visible as $item)
                            @php $active = request()->is($item['match']); @endphp
                            <a href="{{ route($item['route']) }}" @if($active) aria-current="page" @endif
                               class="royal-nav-link {{ $active ? 'is-active' : '' }}">
                                <span class="royal-nav-icon" aria-hidden="true">{{ $item['icon'] }}</span>
                                <span>{{ $item['label'] }}</span>
                                @if($active)<span class="royal-nav-active-dot"></span>@endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </nav>
    <div class="royal-sidebar-bottom border-t border-slate-100 p-4">
        <div class="royal-profile-avatar">{{ strtoupper(substr($currentUser?->name ?? 'AD', 0, 2)) }}</div>
        <div class="min-w-0"><div class="truncate text-xs font-bold text-slate-800">{{ $currentUser?->name ?? 'Admin' }}</div><div class="truncate text-[11px] text-slate-400">{{ $currentUser?->email ?? '' }}</div></div>
    </div>
</aside>

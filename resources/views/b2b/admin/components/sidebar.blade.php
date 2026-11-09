@php
    $permissionService = app(\App\Modules\Identity\Services\PermissionService::class);
    $user = auth()->user();
    $groups = [
        'WORKSPACE' => [
            ['Dashboard','admin.dashboard','dashboard.view','dashboard','▦'],
            ['Bookings','admin.bookings.index','bookings.view','bookings','▤'],
            ['Hotel Search','admin.hotels.index','hotels.view','hotels','⌕'],
        ],
        'MANAGEMENT' => [
            ['Companies','admin.companies.index','companies.view','companies','▥'],
            ['Suppliers','admin.suppliers.index','suppliers.view','suppliers','◇'],
        ],
        'FINANCE' => [
            ['Pricing & Commissions','admin.pricing.index','pricing.royal.manage','pricing','◈'],
            ['Exchange Rates','admin.exchange-rates.index','fx.view','exchange-rates','⇄'],
        ],
        'ADMINISTRATION' => [
            ['Users Management','admin.users.index','users.view','users','♙'],
            ['Roles & Permissions','admin.roles.index','roles.view','roles','⚿'],
        ],
    ];
@endphp
<aside class="rt-sidebar" aria-label="Admin navigation">
    <a href="{{ route('admin.dashboard') }}" class="rt-brand">
        <span class="rt-brand-mark">R<span class="rt-brand-mark-dot">.</span></span>
        <span><strong>Royal Travel</strong><small>ADMIN WORKSPACE</small></span>
    </a>
    <nav class="rt-sidebar-nav" aria-label="Primary">
        @foreach($groups as $group => $items)
            @php
                $visible = collect($items)->filter(fn($item) => \Illuminate\Support\Facades\Route::has($item[1]) && $user && $permissionService->adminCan($user,$item[2]));
            @endphp
            @if($visible->isNotEmpty())
                <div class="rt-nav-group">
                    <div class="rt-nav-heading">{{ $group }}</div>
                    @foreach($visible as $item)
                        @php $active = request()->is('admin/'.$item[3]) || request()->is('admin/'.$item[3].'/*'); @endphp
                        <a href="{{ route($item[1]) }}" @class(['rt-nav-link','is-active'=>$active]) @if($active) aria-current="page" @endif>
                            <span class="rt-nav-icon" aria-hidden="true">{{ $item[4] }}</span>
                            <span>{{ $item[0] }}</span>
                            @if($active)<span class="rt-nav-active-dot" aria-hidden="true"></span>@endif
                        </a>
                    @endforeach
                </div>
            @endif
        @endforeach
    </nav>
    <div class="rt-sidebar-bottom">
        <span class="rt-avatar">{{ $user ? strtoupper(mb_substr($user->name,0,1)) : 'A' }}</span>
        <span class="rt-sidebar-profile"><strong>{{ $user?->name ?? 'Admin' }}</strong><small>{{ $user?->email ?? '' }}</small></span>
    </div>
</aside>

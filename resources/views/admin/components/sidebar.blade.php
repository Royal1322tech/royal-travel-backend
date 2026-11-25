@php
    $permissionService = app(\App\Modules\Identity\Services\PermissionService::class);
    $user = auth()->user();
    $groups = [
        'Workspace' => [
            ['Dashboard', 'admin.dashboard', 'dashboard.view', 'dashboard', 'home'],
            ['Bookings', 'admin.bookings.index', 'bookings.view', 'bookings', 'calendar'],
            ['Support Tickets', 'admin.support.index', 'bookings.view', 'support-tickets', 'headset'],
            ['Hotel Search', 'admin.hotels.index', 'hotels.view', 'hotels', 'hotel'],
            ['Reduce Price Requests', 'admin.price-reduction.index', 'price_requests.view', 'price-reduction-requests', 'tag'],
        ],
        'Management' => [
            ['Companies', 'admin.companies.index', 'companies.view', 'companies', 'building'],
            ['Suppliers', 'admin.suppliers.index', 'suppliers.view', 'suppliers', 'truck'],
        ],
        'Finance' => [
            ['Company Finance', 'admin.companies.finance.index', 'companies.view', 'company-finance', 'wallet'],
            ['Pricing & Commissions', 'admin.pricing.index', 'pricing.royal.manage', 'pricing', 'percent'],
            ['Exchange Rates', 'admin.exchange-rates.index', 'fx.view', 'exchange-rates', 'exchange'],
            ['Loyalty Points', 'admin.loyalty.index', 'loyalty.view', 'loyalty', 'star'],
        ],
        'Administration' => [
            ['Users Management', 'admin.users.index', 'users.view', 'users', 'users'],
            ['Roles & Permissions', 'admin.roles.index', 'roles.view', 'roles', 'shield'],
        ],
    ];
    $icons = [
        'home' => '<path d="m3 10 9-7 9 7v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 21v-7h6v7"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'headset' => '<path d="M3 14v-3a9 9 0 0 1 18 0v3M21 16v2a3 3 0 0 1-3 3h-5"/><rect x="3" y="11" width="4" height="7" rx="2"/><rect x="17" y="11" width="4" height="7" rx="2"/>',
        'hotel' => '<path d="M3 21h18M5 21V3h14v18M9 7h6M9 7v6m6-6v6M9 10h6M10 21v-4h4v4"/>',
        'tag' => '<path d="M3 3h8l10 10-8 8L3 11z"/><circle cx="7.5" cy="7.5" r="1"/><path d="M12 11h4"/>',
        'building' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h2m4 0h2M8 11h2m4 0h2M8 15h2m4 0h2M10 21v-3h4v3"/>',
        'truck' => '<path d="M3 5h12v12H3zM15 9h4l3 4v4h-7"/><circle cx="7" cy="18" r="2" fill="white"/><circle cx="18" cy="18" r="2" fill="white"/>',
        'wallet' => '<rect x="3" y="5" width="18" height="15" rx="3"/><path d="M3 8V6l14-3v2M21 11h-6a3 3 0 0 0 0 6h6M16 14h.01"/>',
        'percent' => '<path d="m5 19 14-14"/><circle cx="7" cy="7" r="3"/><circle cx="17" cy="17" r="3"/>',
        'exchange' => '<path d="M3 7h17m-4-4 4 4-4 4M21 17H4m4-4-4 4 4 4"/>',
        'star' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3zM16 5a3 3 0 0 1 0 6M17 14a5 5 0 0 1 4 5v2"/>',
        'shield' => '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6zM9 12l2 2 4-4"/>',
    ];
@endphp
<style>
.rt-sidebar .rt-sidebar-nav { padding: 12px 12px 18px; }
.rt-sidebar .rt-nav-group { margin-bottom: 22px; }
.rt-sidebar .rt-nav-heading { color:#647084; font-size:11px; font-weight:700; letter-spacing:.09em; text-transform:uppercase; padding: 8px 12px; }
.rt-sidebar .rt-nav-link { display:flex; align-items:center; gap:12px; min-height:44px; padding:10px 12px; margin:3px 0; border-radius:8px; border-inline-start:3px solid transparent; color:#526074; font-size:14px; font-weight:500; line-height:1.4; text-decoration:none; transition:background .15s,color .15s; }
.rt-sidebar .rt-nav-icon { display:inline-flex; align-items:center; justify-content:center; flex:0 0 22px; width:22px; height:22px; color:inherit; }
.rt-sidebar .rt-nav-icon svg { width:21px; height:21px; display:block; }
.rt-sidebar .rt-nav-link:hover { background:#f5f6f8; color:#02143c; }
.rt-sidebar .rt-nav-link.is-active { background:#f3ebe3; color:#a86213; border-inline-start-color:#b7731e; font-weight:600; }
.rt-sidebar .rt-nav-link:focus-visible { outline:2px solid #b7731e; outline-offset:2px; }
.rt-sidebar .rt-nav-active-dot { display:none; }
.rt-sidebar .rt-brand strong { color:#02143c; font-weight:700; }
.rt-sidebar .rt-brand small { color:#647084; font-size:10px; letter-spacing:.08em; }
</style>
<aside class="rt-sidebar" aria-label="Admin navigation">
    <a href="{{ route('admin.dashboard') }}" class="rt-brand">
        <span class="rt-brand-mark">R<span class="rt-brand-mark-dot">.</span></span>
        <span><strong>Royal Travel</strong><small>ADMIN WORKSPACE</small></span>
    </a>
    <nav class="rt-sidebar-nav" aria-label="Primary">
        @foreach($groups as $group => $items)
            @php
                $visible = collect($items)->filter(fn($item) => \Illuminate\Support\Facades\Route::has($item[1]) && $user && $permissionService->adminCan($user, $item[2]));
            @endphp
            @if($visible->isNotEmpty())
                <div class="rt-nav-group">
                    <div class="rt-nav-heading">{{ $group }}</div>
                    @foreach($visible as $item)
                        @php
                            $active = request()->is('admin/' . $item[3]) || request()->is('admin/' . $item[3] . '/*');
                            $isCompanyFinance = $item[0] === 'Company Finance';
                            if ($isCompanyFinance) {
                                $active = request()->routeIs('admin.companies.finance.*');
                            } elseif ($item[1] === 'admin.companies.index') {
                                $active = $active && !request()->routeIs('admin.companies.finance.*');
                            }
                        @endphp
                        <a href="{{ route($item[1]) }}" @class(['rt-nav-link', 'is-active' => $active]) @if($active) aria-current="page" @endif>
                            <span class="rt-nav-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$item[4]] !!}</svg>
                            </span>
                            <span>{{ $item[0] }}</span>
                            @if($active)<span class="rt-nav-active-dot" aria-hidden="true"></span>@endif
                        </a>
                    @endforeach
                </div>
            @endif
        @endforeach
    </nav>
    <div class="rt-sidebar-bottom">
        <span class="rt-avatar">{{ $user ? strtoupper(mb_substr($user->name, 0, 1)) : 'A' }}</span>
        <span class="rt-sidebar-profile"><strong>{{ $user?->name ?? 'Admin' }}</strong><small>{{ $user?->email ?? '' }}</small></span>
    </div>
</aside>

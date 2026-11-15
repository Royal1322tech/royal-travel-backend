<header class="royal-topbar flex items-center justify-between gap-4 border-b border-slate-100 bg-white px-5 py-3 sm:px-8">
    <div class="min-w-0">
        <div class="royal-topbar-eyebrow">ROYAL TRAVEL / ADMIN CONSOLE</div>
        <div class="royal-topbar-title">@yield('title', 'Dashboard')</div>
    </div>
    <div class="flex items-center gap-3">
        <div class="royal-topbar-date hidden sm:block">{{ now()->format('D, d M Y') }}</div>
        <div class="royal-topbar-avatar" aria-hidden="true">{{ strtoupper(substr(auth()->user()?->name ?? 'A', 0, 1)) }}</div>
        <div class="hidden text-right md:block">
            <div class="text-xs font-semibold text-slate-800">{{ auth()->user()?->name ?? 'Admin' }}</div>
            <div class="text-[11px] text-slate-400">Administrator</div>
        </div>
        <form method="POST" action="{{ route('admin.logout') }}">@csrf
            <button type="submit" class="royal-logout" title="Sign out">Log out</button>
        </form>
    </div>
</header>

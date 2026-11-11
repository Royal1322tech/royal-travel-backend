<header class="rt-topbar">
    <div class="rt-topbar-context"><span class="rt-topbar-dot"></span><span>Royal Travel</span><span class="rt-topbar-separator">/</span><strong>@yield('title','Dashboard')</strong></div>
    <div class="rt-topbar-actions">
        <span class="rt-topbar-date">{{ now()->format('d M Y') }}</span>
        <span class="rt-topbar-user">{{ auth()->user()?->name ?? 'Admin' }}</span>
        <form method="POST" action="{{ route('admin.logout') }}">@csrf<button type="submit" class="rt-logout">Log out</button></form>
    </div>
</header>

<header class="flex min-h-20 items-center justify-between gap-4 border-b border-slate-100 bg-white px-5 lg:px-8">

    <div>
        <p class="text-xs font-medium text-slate-400">
            {{ request()->is('ui-preview/b2b/dashboard') ? 'Overview' : 'Management' }}
        </p>
        <h1 class="mt-1 text-xl font-bold tracking-tight text-[#02143C]">
            @if (request()->is('ui-preview/b2b/hotels') || request()->is('ui-preview/b2b/hotels/*'))
                Hotels
            @elseif (request()->is('ui-preview/b2b/all-bookings'))
                Bookings
            @elseif (request()->is('ui-preview/b2b/bookings/*'))
                Booking Detail
            @elseif (request()->is('ui-preview/b2b/bookings'))
                Hotel Offline Requests
            @else
                Dashboard
            @endif
        </h1>
    </div>

    <div class="flex items-center gap-3 sm:gap-5">

        {{-- Search --}}
        <div class="hidden items-center gap-3 rounded-xl border border-slate-200 bg-[#F8F9FC] px-4 py-2.5 md:flex">
            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="11" cy="11" r="7"></circle>
                <path d="m16 16 4 4"></path>
            </svg>
            <input type="search" placeholder="Search anything..."
                aria-label="Search"
                class="w-40 bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400 xl:w-56">
        </div>

        {{-- Notifications: visual preview only --}}
        <div class="relative flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-600">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z"></path>
                <path d="M10 21h4"></path>
            </svg>
            <span class="absolute end-2 top-2 h-2 w-2 rounded-full bg-[#B7731E]"></span>
        </div>

        <div class="hidden h-8 w-px bg-slate-200 sm:block"></div>

        {{-- Demo profile --}}
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#E8EDF5] text-sm font-bold text-[#02143C]">
                RD
            </div>
            <div class="hidden sm:block">
                <p class="text-sm font-semibold text-[#02143C]">Robbi Darwis</p>
                <p class="text-xs text-slate-400">Company User</p>
            </div>
        </div>

    </div>
</header>

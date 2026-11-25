<!doctype html>
<html lang="en">
<head>

    <meta charset="utf-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>@yield('title', 'Company Portal') | Royal Travel</title>

    <style>

        :root {
            --primary: #10254b;
            --primary-light: #29436b;
            --gold: #b7731e;
            --background: #f5f7fb;
            --white: #ffffff;
            --muted: #64748b;
            --border: #e1e6ef;
            --sidebar-width: 260px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font: 15px system-ui, -apple-system, sans-serif;
            color: var(--primary);
            background: var(--background);
            min-height: 100vh;
        }

        /* =========================
           SIDEBAR
        ========================= */

        .portal-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: var(--primary);
            color: white;
            display: flex;
            flex-direction: column;
            padding: 26px 18px;
            overflow-y: auto;
            z-index: 100;
        }

        .portal-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 8px 26px;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }

        .portal-brand-icon {
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            background: var(--gold);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .portal-brand-icon svg {
            width: 23px;
            height: 23px;
        }

        .portal-brand-name {
            font-size: 14px;
            font-weight: 750;
            letter-spacing: .5px;
        }

        .portal-brand-subtitle {
            color: #9cacc6;
            font-size: 12px;
            margin-top: 5px;
        }

        .portal-navigation {
            margin-top: 28px;
        }

        .portal-nav-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            color: #8196b7;
            padding: 0 13px;
            margin-bottom: 12px;
        }

        .portal-nav-link {
            position: relative;
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 13px 14px;
            margin-bottom: 6px;
            color: #bcc9dd;
            text-decoration: none;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 500;
            transition: all .2s ease;
        }

        .portal-nav-link svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            stroke-width: 1.7;
        }

        .portal-nav-link:hover {
            color: white;
            background: rgba(255,255,255,.07);
        }

        .portal-nav-link.active {
            background: var(--primary-light);
            color: white;
            font-weight: 600;
        }

        .portal-nav-link.active svg {
            color: #e4ad68;
        }

        .portal-nav-link.active::after {
            content: '';
            position: absolute;
            right: 0;
            top: 12px;
            bottom: 12px;
            width: 3px;
            border-radius: 3px;
            background: #d99b50;
        }

        .portal-nav-divider {
            height: 1px;
            background: rgba(255,255,255,.1);
            margin: 24px 10px;
        }

        .portal-sidebar-footer {
            margin-top: auto;
            padding-top: 24px;
        }

        .portal-logout-form {
            margin: 0;
            padding-top: 18px;
            border-top: 1px solid rgba(255,255,255,.1);
        }

        .portal-logout-button {
            display: flex;
            align-items: center;
            gap: 13px;
            width: 100%;
            background: transparent;
            border: none;
            color: #bcc9dd;
            padding: 13px 14px;
            border-radius: 9px;
            font-size: 14px;
            text-align: left;
            cursor: pointer;
        }

        .portal-logout-button:hover {
            background: rgba(239,68,68,.12);
            color: #fca5a5;
        }

        .portal-logout-button svg {
            width: 20px;
            height: 20px;
        }

        /* =========================
           MAIN WORKSPACE
        ========================= */

        .portal-main {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            min-width: 0;
        }

        .portal-header {
            min-height: 82px;
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 18px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .portal-company-name {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary);
        }

        .portal-company-caption {
            font-size: 12px;
            color: var(--muted);
            margin-top: 5px;
        }

        .portal-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .portal-user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #edf1f7;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 17px;
        }

        .portal-user-name {
            font-size: 14px;
            font-weight: 600;
        }

        .portal-user-role {
            font-size: 12px;
            color: var(--muted);
            margin-top: 4px;
        }

        .portal-content {
            padding: 30px 32px;
        }

        .portal-breadcrumb {
            display: flex;
            align-items: center;
            gap: 9px;
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 24px;
        }

        .portal-breadcrumb svg {
            width: 14px;
            height: 14px;
        }

        .portal-breadcrumb-current {
            color: var(--primary);
            font-weight: 600;
        }

        /* =========================
           EXISTING SHARED COMPONENTS
        ========================= */

        .cards {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
        }

        .number {
            font-size: 32px;
            font-weight: 700;
            margin-top: 12px;
        }

        button {
            padding: 10px 16px;
            border: 1px solid #ccd5e2;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font: inherit;
        }

        .portal-content > h1 {
            font-size: 28px;
            font-weight: 750;
            letter-spacing: -.5px;
            margin: 0 0 12px;
        }

        /* =========================
           MOBILE
        ========================= */

        .portal-mobile-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            padding: 8px;
        }

        .portal-mobile-toggle svg {
            width: 22px;
            height: 22px;
        }

        .portal-overlay {
            display: none;
        }

        @media (max-width: 900px) {

            .portal-sidebar {
                transform: translateX(-100%);
                transition: transform .25s ease;
            }

            .portal-sidebar.is-open {
                transform: translateX(0);
            }

            .portal-main {
                margin-left: 0;
            }

            .portal-mobile-toggle {
                display: flex;
            }

            .portal-header {
                padding: 16px 20px;
            }

            .portal-content {
                padding: 24px 20px;
            }

            .portal-overlay.is-visible {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(15,23,42,.45);
                z-index: 99;
            }
        }

        @media (max-width: 500px) {

            .portal-user-details {
                display: none;
            }

            .portal-company-name {
                font-size: 13px;
            }

            .portal-content {
                padding: 20px 16px;
            }
        }

    </style>

    @stack('styles')

</head>

<body>

    <!-- SIDEBAR -->

    <aside class="portal-sidebar" id="portalSidebar">

        <div class="portal-brand">

            <div class="portal-brand-icon">

                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.7"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <path d="m2 8 5 4 5-8 5 8 5-4-3 12H5L2 8Z"/>
                    <path d="M5 20h14"/>

                </svg>

            </div>

            <div>
                <div class="portal-brand-name">
                    ROYAL TRAVEL
                </div>

                <div class="portal-brand-subtitle">
                    Company Portal
                </div>
            </div>

        </div>

        <nav class="portal-navigation">

            <div class="portal-nav-label">
                WORKSPACE
            </div>

            <!-- Dashboard -->

            <a href="{{ route('company-portal.dashboard') }}"
               class="portal-nav-link {{ request()->routeIs('company-portal.dashboard') ? 'active' : '' }}">

                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                    <rect x="14" y="14" width="7" height="7" rx="1"/>

                </svg>

                Dashboard

            </a>

            <!-- Bookings -->

            <a href="{{ route('company-portal.bookings.index') }}"
               class="portal-nav-link {{ request()->routeIs('company-portal.bookings.*') ? 'active' : '' }}">

                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <rect x="3" y="5" width="18" height="16" rx="2"/>
                    <path d="M16 3v4M8 3v4M3 10h18"/>
                    <path d="m9 15 2 2 4-4"/>

                </svg>

                Bookings

            </a>

            <!-- Employees -->

            <a href="{{ route('company-portal.employees.index') }}"
               class="portal-nav-link {{ request()->routeIs('company-portal.employees.*') ? 'active' : '' }}">

                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>

                </svg>

                Employees

            </a>

            <!-- Customers -->

            <a href="{{ route('company-portal.customers.index') }}"
               class="portal-nav-link {{ request()->routeIs('company-portal.customers.*') ? 'active' : '' }}">

                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <circle cx="12" cy="9" r="3"/>
                    <path d="M6 18v-1a6 6 0 0 1 12 0v1"/>

                </svg>

                Customers

            </a>

            <!-- Markup -->

            <a href="{{ route('company-portal.markup.index') }}"
               class="portal-nav-link {{ request()->routeIs('company-portal.markup.*') ? 'active' : '' }}">

                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <path d="m19 5-14 14"/>
                    <circle cx="7" cy="7" r="3"/>
                    <circle cx="17" cy="17" r="3"/>

                </svg>

                Markup

            </a>

            <div class="portal-nav-divider"></div>

            <div class="portal-nav-label">
                SETTINGS
            </div>

            <!-- Company Profile -->

            <a href="{{ route('company-portal.profile.index') }}"
               class="portal-nav-link {{ request()->routeIs('company-portal.profile.*') ? 'active' : '' }}">

                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <rect x="4" y="3" width="16" height="18" rx="2"/>
                    <path d="M9 21v-4h6v4"/>
                    <path d="M8 7h2m4 0h2M8 12h2m4 0h2"/>

                </svg>

                Company Profile

            </a>

        </nav>

        <!-- Logout -->

        <div class="portal-sidebar-footer">

            <form method="POST"
                  action="{{ route('company-portal.logout') }}"
                  class="portal-logout-form">

                @csrf

                <button type="submit"
                        class="portal-logout-button">

                    <svg viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="1.7"
                         stroke-linecap="round"
                         stroke-linejoin="round">

                        <path d="M10 17l5-5-5-5"/>
                        <path d="M15 12H3"/>
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>

                    </svg>

                    Sign out

                </button>

            </form>

        </div>

    </aside>

    <div class="portal-overlay"
         id="portalOverlay"></div>

    <!-- MAIN -->

    <main class="portal-main">

        <header class="portal-header">

            <div style="display:flex;align-items:center;gap:14px">

                <button type="button"
                        class="portal-mobile-toggle"
                        id="portalMenuButton"
                        aria-label="Toggle navigation"
                        aria-controls="portalSidebar"
                        aria-expanded="false">

                    <svg viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="2"
                         stroke-linecap="round">

                        <path d="M4 6h16M4 12h16M4 18h16"/>

                    </svg>

                </button>

                <div>

                    <div class="portal-company-name">
                        {{ $company->display_name ?: $company->legal_name }}
                    </div>

                    <div class="portal-company-caption">
                        Company workspace
                    </div>

                </div>

            </div>

            <div class="portal-user">

                <div class="portal-user-avatar">

                    {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}

                </div>

                <div class="portal-user-details">

                    <div class="portal-user-name">
                        {{ auth()->user()->name }}
                    </div>

                    <div class="portal-user-role">
                        Company Owner
                    </div>

                </div>

            </div>

        </header>

        <div class="portal-content">

            <div class="portal-breadcrumb">

                <span>Company Portal</span>

                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="2">

                    <path d="m9 18 6-6-6-6"/>

                </svg>

                <span class="portal-breadcrumb-current">
                    @yield('title', 'Dashboard')
                </span>

            </div>

            @yield('content')

        </div>

    </main>

    <script>

        const sidebar = document.getElementById('portalSidebar');
        const overlay = document.getElementById('portalOverlay');
        const menuButton = document.getElementById('portalMenuButton');

        function setPortalMenu(open) {
            sidebar.classList.toggle('is-open', open);
            overlay.classList.toggle('is-visible', open);
            menuButton.setAttribute('aria-expanded', String(open));
        }

        menuButton.addEventListener('click', function () {
            setPortalMenu(!sidebar.classList.contains('is-open'));
        });

        overlay.addEventListener('click', function () {
            setPortalMenu(false);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setPortalMenu(false);
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 900) {
                setPortalMenu(false);
            }
        });

    </script>

    @stack('scripts')

</body>
</html>
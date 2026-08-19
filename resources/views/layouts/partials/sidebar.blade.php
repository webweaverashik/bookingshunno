<div id="kt_app_sidebar" class="app-sidebar flex-column" data-kt-drawer="true" data-kt-drawer-name="app-sidebar"
    data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="225px"
    data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_app_sidebar_mobile_toggle">

    <div class="app-sidebar-logo px-6" id="kt_app_sidebar_logo">
        <a href="{{ route('admin.dashboard') }}" class="d-flex align-items-center gap-3">
            <img alt="" src="{{ asset('img/shunno-white.png') }}" class="h-50px rounded-circle">
            <span class="text-white fw-bold fs-6 d-none d-lg-inline">Shunno Art Cafe</span>
        </a>

        <div id="kt_app_sidebar_toggle"
            class="app-sidebar-toggle btn btn-icon btn-shadow btn-sm btn-color-muted btn-active-color-primary body-bg h-30px w-30px position-absolute top-50 start-100 translate-middle rotate"
            data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body"
            data-kt-toggle-name="app-sidebar-minimize">
            <i class="ki-outline ki-black-left-line fs-3 rotate-180"></i>
        </div>
    </div>

    <div class="app-sidebar-menu overflow-hidden flex-column-fluid">
        <div id="kt_app_sidebar_menu_wrapper" class="app-sidebar-wrapper hover-scroll-overlay-y my-5"
            data-kt-scroll="true" data-kt-scroll-activate="true" data-kt-scroll-height="auto"
            data-kt-scroll-dependencies="#kt_app_sidebar_logo, #kt_app_sidebar_footer"
            data-kt-scroll-wrappers="#kt_app_sidebar_menu" data-kt-scroll-offset="5px">

            <div class="menu menu-column menu-rounded menu-sub-indention fw-semibold fs-6" id="#kt_app_sidebar_menu"
                data-kt-menu="true">

                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                        href="{{ route('admin.dashboard') }}">
                        <span class="menu-icon"><i class="ki-outline ki-element-11 fs-2"></i></span>
                        <span class="menu-title">Dashboard</span>
                    </a>
                </div>

                <div class="menu-item pt-5">
                    <div class="menu-content"><span class="menu-heading fw-bold text-uppercase fs-7">Reservations</span>
                    </div>
                </div>
                @can('reservations.view')
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.reservations.*') ? 'active' : '' }}"
                            href="{{ route('admin.reservations.index') }}">
                            <span class="menu-icon"><i class="ki-outline ki-calendar-8 fs-2"></i></span>
                            <span class="menu-title">Reservations</span>
                        </a>
                    </div>
                @endcan
                
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('admin.visitors.*') ? 'active' : '' }}"
                        href="{{ route('admin.visitors.index') }}">
                        <span class="menu-icon"><i class="ki-outline ki-profile-user fs-2"></i></span>
                        <span class="menu-title">Visitors</span>
                    </a>
                </div>

                <div class="menu-item pt-5">
                    <div class="menu-content"><span class="menu-heading fw-bold text-uppercase fs-7">Catalogue</span>
                    </div>
                </div>
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('admin.workshops.*') ? 'active' : '' }}"
                        href="{{ route('admin.workshops.index') }}">
                        <span class="menu-icon"><i class="ki-outline ki-color-swatch fs-2"></i></span>
                        <span class="menu-title">Workshops</span>
                    </a>
                </div>
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('admin.availability.*') ? 'active' : '' }}"
                        href="{{ route('admin.availability.index') }}">
                        <span class="menu-icon"><i class="ki-outline ki-time fs-2"></i></span>
                        <span class="menu-title">Availability</span>
                    </a>
                </div>

                <div class="menu-item pt-5">
                    <div class="menu-content"><span class="menu-heading fw-bold text-uppercase fs-7">Money</span></div>
                </div>
                @can('payments.view')
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}"
                            href="{{ route('admin.payments.index') }}">
                            <span class="menu-icon"><i class="ki-outline ki-credit-cart fs-2"></i></span>
                            <span class="menu-title">Payments</span>
                        </a>
                    </div>
                @endcan
                @can('vouchers.view')
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.vouchers.*') ? 'active' : '' }}"
                            href="{{ route('admin.vouchers.index') }}">
                            <span class="menu-icon"><i class="ki-outline ki-gift fs-2"></i></span>
                            {{-- "Vouchers", not "Gift vouchers": this screen holds
                                 café credit too, and the narrower label would send
                                 staff looking elsewhere for coupons. --}}
                            <span class="menu-title">Vouchers</span>
                        </a>
                    </div>
                @endcan

                {{-- PHASE 16. Moved out of the Admin-only block and onto its
                     own permission: Manager holds reports.view and reports.export,
                     because whoever runs the floor is the person asked how last
                     month went. @role('Admin') here would have hidden a screen
                     the seeder had already granted them. --}}
                @can('reports.view')
                    <div class="menu-item pt-5">
                        <div class="menu-content"><span
                                class="menu-heading fw-bold text-uppercase fs-7">Insight</span></div>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}"
                            href="{{ route('admin.reports.index') }}">
                            <span class="menu-icon">
                                <i class="ki-outline ki-chart-simple-3 fs-2"></i>
                            </span>
                            <span class="menu-title">Reports</span>
                        </a>
                    </div>
                @endcan

                @can('settings.view')
                    <div class="menu-item pt-5">
                        <div class="menu-content"><span
                                class="menu-heading fw-bold text-uppercase fs-7">Administration</span></div>
                    </div>
                    {{-- PHASE 20 — staff accounts. Under Administration and
                         gated on users.view, which is Admin-only: a Manager who
                         could create accounts could create an Admin, and the
                         role split would be decoration. --}}
                    @can('users.view')
                        <div class="menu-item">
                            <a class="menu-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                                href="{{ route('admin.users.index') }}">
                                <span class="menu-icon">
                                    <i class="ki-outline ki-people fs-2"></i>
                                </span>
                                <span class="menu-title">Staff</span>
                            </a>
                        </div>
                    @endcan

                    {{-- Maintenance: artisan from the browser, because the live
                         host gives no shell. Its own permission rather than
                         settings.view — reading the SMTP password and migrating
                         the database are different powers. --}}
                    @can('system.maintenance')
                        <div class="menu-item">
                            <a class="menu-link {{ request()->routeIs('admin.maintenance.*') ? 'active' : '' }}"
                                href="{{ route('admin.maintenance.index') }}">
                                <span class="menu-icon">
                                    <i class="ki-outline ki-wrench fs-2"></i>
                                </span>
                                <span class="menu-title">Maintenance</span>
                            </a>
                        </div>
                    @endcan

                    <div class="menu-item">
                        {{-- PHASE 17. The placeholder read routeIs('settings.*'),
                             which never matched: routes in this group carry the
                             'admin.' name prefix, so the pattern has to be
                             'admin.settings.*'. The href was '#'. --}}
                        <a class="menu-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"
                            href="{{ route('admin.settings.index') }}">
                            <span class="menu-icon">
                                <i class="ki-outline ki-setting-2 fs-2"></i>
                            </span>
                            <span class="menu-title">Settings</span>
                        </a>
                    </div>
                @endcan

                {{-- PHASE 19 — My profile is NOT in this sidebar. It lives in the
                     avatar menu in layouts/partials/header.blade.php, which is
                     where people look for their own account. The sidebar is for
                     the studio's work. --}}
            </div>
        </div>
    </div>

    <div class="app-sidebar-footer flex-column-auto pt-2 pb-6 px-6" id="kt_app_sidebar_footer">
        <a href="{{ route('home') }}" target="_blank"
            class="btn btn-flex flex-center btn-custom btn-primary overflow-hidden text-nowrap px-0 h-40px w-100">
            <span class="btn-label">View the website</span>
            <i class="ki-outline ki-exit-right-corner fs-2 m-0"></i>
        </a>

        <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" style="display: none;">
            @csrf
        </form>
    </div>
</div>

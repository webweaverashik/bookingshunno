<div id="kt_app_header" class="app-header">
    <div class="app-container container-fluid d-flex align-items-stretch justify-content-between">

        <div class="d-flex align-items-center d-lg-none ms-n3" title="Show sidebar menu">
            <div class="btn btn-icon btn-active-color-primary w-35px h-35px" id="kt_app_sidebar_mobile_toggle">
                <i class="ki-outline ki-abstract-14 fs-2"></i>
            </div>
        </div>

        <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
            <h1 class="text-dark fw-bold m-0 fs-3">@yield('page-title', 'Dashboard')</h1>
        </div>

        <div class="app-navbar flex-shrink-0 gap-2 gap-lg-3">

            @include('layouts.partials.theme_mode')

            <div class="app-navbar-item ms-1 ms-lg-3">
                <div class="cursor-pointer symbol symbol-35px" data-kt-menu-trigger="click"
                     data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end">
                    <div class="symbol-label fs-6 fw-bold bg-light-primary text-primary">
                        {{ strtoupper(substr(auth()->user()?->name ?? '?', 0, 1)) }}
                    </div>
                </div>

                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px">
                    <div class="menu-item px-3">
                        <div class="menu-content d-flex align-items-center px-3">
                            <div class="symbol symbol-50px me-5">
                                <div class="symbol-label fs-3 fw-bold bg-light-primary text-primary">
                                    {{ strtoupper(substr(auth()->user()?->name ?? '?', 0, 1)) }}
                                </div>
                            </div>
                            <div class="d-flex flex-column">
                                <div class="fw-bold d-flex align-items-center fs-5">
                                    {{ auth()->user()?->name }}
                                    {{--
                                        Null-safe. The old template called
                                        ucfirst(getRoleNames()->first()) directly,
                                        which threw for any user without a role.
                                    --}}
                                    <span class="badge badge-light-success fw-bold fs-8 px-2 py-1 ms-2">
                                        {{ auth()->user()?->getRoleNames()->first() ?? 'No role' }}
                                    </span>
                                </div>
                                <span class="fw-semibold text-muted fs-7">{{ auth()->user()?->email }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="separator my-2"></div>

                    <div class="menu-item px-5">
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button type="submit" class="menu-link px-5 border-0 bg-transparent w-100 text-start">
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

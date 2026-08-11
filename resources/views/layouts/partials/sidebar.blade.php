<div id="kt_app_sidebar" class="app-sidebar flex-column" data-kt-drawer="true" data-kt-drawer-name="app-sidebar"
    data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="225px"
    data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_app_sidebar_mobile_toggle">

    <!--begin::Logo-->
    <div class="app-sidebar-logo px-6" id="kt_app_sidebar_logo">
        <a href="{{ route('dashboard') }}">
            <img alt="Logo" src="{{ asset('assets/img/logo-dark.png') }}" class="h-50px app-sidebar-logo-default" />
            <img alt="Logo" src="{{ asset('assets/img/icon.png') }}" class="h-20px app-sidebar-logo-minimize" />
        </a>

        <div id="kt_app_sidebar_toggle"
            class="app-sidebar-toggle btn btn-icon btn-shadow btn-sm btn-color-muted btn-active-color-primary h-30px w-30px position-absolute top-50 start-100 translate-middle rotate"
            data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body"
            data-kt-toggle-name="app-sidebar-minimize">
            <i class="ki-outline ki-black-left-line fs-3 rotate-180"></i>
        </div>
    </div>
    <!--end::Logo-->

    <!--begin::Sidebar menu-->
    <div class="app-sidebar-menu overflow-hidden flex-column-fluid">
        <div id="kt_app_sidebar_menu_wrapper" class="app-sidebar-wrapper">
            <div id="kt_app_sidebar_menu_scroll" class="scroll-y my-5 mx-3" data-kt-scroll="true"
                data-kt-scroll-activate="true" data-kt-scroll-height="auto"
                data-kt-scroll-dependencies="#kt_app_sidebar_logo, #kt_app_sidebar_footer"
                data-kt-scroll-wrappers="#kt_app_sidebar_menu" data-kt-scroll-offset="5px"
                data-kt-scroll-save-state="true">

                <div class="menu menu-column menu-rounded menu-sub-indention fw-semibold fs-6" id="kt_app_sidebar_menu"
                    data-kt-menu="true" data-kt-menu-expand="false">

                    <!--begin::Dashboard-->
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                            href="{{ route('dashboard') }}" id="dashboard_link">
                            <span class="menu-icon">
                                <i class="ki-outline ki-chart-pie-4 fs-2"></i>
                            </span>
                            <span class="menu-title">Dashboard</span>
                        </a>
                    </div>
                    <!--end::Dashboard-->

                    <!--begin::Employee Info-->
                    @canany(['employee.view', 'employee.create', 'employee_salary.view'])
                        <div data-kt-menu-trigger="click"
                            class="menu-item menu-accordion {{ request()->routeIs('employees.*', 'employee-salary.*') ? 'here show' : '' }}"
                            id="employee_info_menu">
                            <span class="menu-link">
                                <span class="menu-icon">
                                    <i class="ki-outline ki-people fs-1"></i>
                                </span>
                                <span class="menu-title">Employee Info</span>
                                <span class="menu-arrow"></span>
                            </span>

                            <div class="menu-sub menu-sub-accordion">

                                @can('employee.view')
                                    <div class="menu-item">
                                        <a class="menu-link {{ request()->routeIs('employees.*') ? 'active' : '' }}"
                                            id="all_employees_link" href="{{ route('employees.index') }}">
                                            <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                            <span class="menu-title">All Employees</span>
                                        </a>
                                    </div>
                                @endcan

                                @can('employee_salary.view')
                                    <div class="menu-item">
                                        <a class="menu-link {{ request()->routeIs('employee-salary.*') ? 'active' : '' }}"
                                            id="salary_history_link" href="{{ route('employee-salary.index') }}">
                                            <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                            <span class="menu-title">Salary History</span>
                                        </a>
                                    </div>
                                @endcan

                            </div>
                        </div>
                    @endcanany
                    <!--end::Employee Info-->

                    <!--begin::CPF Operation-->
                    @canany(['cpf_contribution.view', 'cpf_ledger.view'])
                        <div data-kt-menu-trigger="click"
                            class="menu-item menu-accordion {{ request()->routeIs('cpf-contributions.*', 'cpf-ledger.*') ? 'here show' : '' }}"
                            id="cpf_operation_menu">
                            <span class="menu-link">
                                <span class="menu-icon">
                                    <i class="ki-outline ki-wallet fs-2"></i>
                                </span>
                                <span class="menu-title">CPF Operation</span>
                                <span class="menu-arrow"></span>
                            </span>

                            <div class="menu-sub menu-sub-accordion">

                                @can('cpf_contribution.view')
                                    <div class="menu-item">
                                        <a class="menu-link {{ request()->routeIs('cpf-contributions.*') ? 'active' : '' }}"
                                            id="monthly_contributions_link" href="{{ route('cpf-contributions.index') }}">
                                            <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                            <span class="menu-title">Monthly Contributions</span>
                                        </a>
                                    </div>
                                @endcan

                                @can('cpf_ledger.view')
                                    <div class="menu-item">
                                        <a class="menu-link {{ request()->routeIs('cpf-ledger.index', 'cpf-ledger.show') ? 'active' : '' }}"
                                            id="cpf_ledger_link" href="{{ route('cpf-ledger.index') }}">
                                            <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                            <span class="menu-title">CPF Ledger</span>
                                        </a>
                                    </div>
                                @endcan

                                @can('cpf_ledger.view')
                                    <div class="menu-item">
                                        <a class="menu-link {{ request()->routeIs('cpf-ledger.transactions') ? 'active' : '' }}"
                                            id="ledger_transactions_link" href="{{ route('cpf-ledger.transactions') }}">
                                            <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                            <span class="menu-title">Ledger Transactions</span>
                                        </a>
                                    </div>
                                @endcan

                            </div>
                        </div>
                    @endcanany
                    <!--end::CPF Operation-->

                    <!--begin::CPF Advance/Loan-->
                    @canany(['cpf_advance.view', 'cpf_advance.create', 'cpf_advance.approve', 'cpf_advance.recovery'])
                        <div data-kt-menu-trigger="click"
                            class="menu-item menu-accordion {{ request()->routeIs('cpf-advances.*') ? 'here show' : '' }}"
                            id="cpf_advance_menu">
                            <span class="menu-link">
                                <span class="menu-icon">
                                    <i class="ki-outline ki-credit-cart fs-3"></i>
                                </span>
                                <span class="menu-title">CPF Advance/Loan</span>
                                <span class="menu-arrow"></span>
                            </span>

                            <div class="menu-sub menu-sub-accordion">

                                @can('cpf_advance.view')
                                    <div class="menu-item">
                                        <a class="menu-link {{ request()->routeIs('cpf-advances.index', 'cpf-advances.create', 'cpf-advances.edit', 'cpf-advances.show') ? 'active' : '' }}"
                                            href="{{ route('cpf-advances.index') }}" id="advance_applications_link">
                                            <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                            <span class="menu-title">Advance Applications</span>
                                        </a>
                                    </div>
                                @endcan

                                @can('cpf_advance.view')
                                    <div class="menu-item">
                                        <a class="menu-link {{ request()->routeIs('cpf-advances.outstanding') ? 'active' : '' }}"
                                            href="{{ route('cpf-advances.outstanding') }}" id="outstanding_advances_link">
                                            <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                            <span class="menu-title">Outstanding Advances</span>
                                        </a>
                                    </div>
                                @endcan

                                @can('cpf_advance.recovery')
                                    <div class="menu-item">
                                        <a class="menu-link {{ request()->routeIs('cpf-advances.recovery.*') ? 'active' : '' }}"
                                            href="{{ route('cpf-advances.recovery.index') }}" id="recovery_posting_link">
                                            <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                            <span class="menu-title">Recovery Posting</span>
                                        </a>
                                    </div>
                                @endcan

                            </div>
                        </div>
                    @endcanany
                    <!--end::CPF Advance/Loan-->

                    <!--begin::Final Settlement-->
                    @canany(['cpf_settlement.view', 'cpf_settlement.create', 'cpf_settlement.approve'])
                        <div data-kt-menu-trigger="click"
                            class="menu-item menu-accordion {{ request()->routeIs('cpf-settlements.*') ? 'here show' : '' }}"
                            id="cpf_settlement_menu">
                            <span class="menu-link">
                                <span class="menu-icon">
                                    <i class="ki-outline ki-exit-right-corner fs-2"></i>
                                </span>
                                <span class="menu-title">Final Settlement</span>
                                <span class="menu-arrow"></span>
                            </span>

                            <div class="menu-sub menu-sub-accordion">

                                @can('cpf_settlement.view')
                                    <div class="menu-item">
                                        <a class="menu-link {{ request()->routeIs('cpf-settlements.index', 'cpf-settlements.show') ? 'active' : '' }}"
                                            href="{{ route('cpf-settlements.index') }}" id="settlements_link">
                                            <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                            <span class="menu-title">Settlements</span>
                                        </a>
                                    </div>
                                @endcan

                                @can('cpf_settlement.create')
                                    <div class="menu-item">
                                        <a class="menu-link {{ request()->routeIs('cpf-settlements.create', 'cpf-settlements.edit') ? 'active' : '' }}"
                                            href="{{ route('cpf-settlements.create') }}" id="new_settlement_link">
                                            <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                            <span class="menu-title">New Settlement</span>
                                        </a>
                                    </div>
                                @endcan

                            </div>
                        </div>
                    @endcanany
                    <!--end::Final Settlement-->

                    <!--begin::Interest Management-->
                    @can('bank_interest.view')
                        <div data-kt-menu-trigger="click"
                            class="menu-item menu-accordion {{ request()->routeIs('bank-interest.*') ? 'here show' : '' }}"
                            id="interest_management_menu">
                            <span class="menu-link">
                                <span class="menu-icon">
                                    <i class="ki-outline ki-percentage fs-2"></i>
                                </span>
                                <span class="menu-title">Interest Management</span>
                                <span class="menu-arrow"></span>
                            </span>

                            <div class="menu-sub menu-sub-accordion">

                                <div class="menu-item">
                                    <a class="menu-link {{ request()->routeIs('bank-interest.index', 'bank-interest.show') ? 'active' : '' }}"
                                        id="distribution_history_link" href="{{ route('bank-interest.index') }}">
                                        <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                        <span class="menu-title">Distributions</span>
                                    </a>
                                </div>

                                @can('bank_interest.create')
                                    <div class="menu-item">
                                        <a class="menu-link {{ request()->routeIs('bank-interest.distribute') ? 'active' : '' }}"
                                            id="interest_distribution_link" href="{{ route('bank-interest.distribute') }}">
                                            <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                            <span class="menu-title">New Distribution</span>
                                        </a>
                                    </div>
                                @endcan

                            </div>
                        </div>
                    @endcan
                    <!--end::Interest Management-->

                    <!--begin::Reports-->
                    @canany(['report.view'])
                        @php($reportsOpen = request()->routeIs('reports.*') || request()->routeIs('audit-logs.*') || request()->routeIs('scheduled-tasks.index'))
                        <div data-kt-menu-trigger="click"
                            class="menu-item menu-accordion {{ $reportsOpen ? 'here show' : '' }}">
                            <span class="menu-link">
                                <span class="menu-icon">
                                    <i class="ki-outline ki-chart-simple fs-2"></i>
                                </span>
                                <span class="menu-title">Reports</span>
                                <span class="menu-arrow"></span>
                            </span>
                            <div class="menu-sub menu-sub-accordion">
                                {{-- All reports & certificates --}}
                                @can('report.view')
                                    <div class="menu-item">
                                        <a class="menu-link {{ request()->routeIs('reports.*') ? 'active' : '' }}"
                                            href="{{ route('reports.index') }}" id="reports_link">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                            <span class="menu-title">Reports &amp; Certificates</span>
                                        </a>
                                    </div>
                                @endcan

                                {{-- Audit & login logs (Admin only) --}}
                                @role('Admin')
                                    <div class="menu-item">
                                        <a class="menu-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}"
                                            href="{{ route('audit-logs.index') }}" id="audit_logs_link">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                            <span class="menu-title">Audit Logs</span>
                                        </a>
                                    </div>

                                    <div class="menu-item">
                                        <a class="menu-link {{ request()->routeIs('scheduled-tasks.*') ? 'active' : '' }}"
                                            href="{{ route('scheduled-tasks.index') }}" id="scheduled_tasks_link">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                            <span class="menu-title">Scheduled Tasks</span>
                                        </a>
                                    </div>
                                @endrole
                            </div>
                        </div>
                    @endcanany
                    <!--end::Reports-->

                    <!--begin::Settings Section-->
                    @canany(['user.view', 'user.create', 'setting.view', 'setting.update'])
                        <div class="menu-item pt-5">
                            <div class="menu-content">
                                <span class="menu-heading fw-bold text-uppercase fs-7">Settings</span>
                            </div>
                        </div>
                    @endcanany
                    <!--end::Settings Section heading-->

                    <!--begin::Users-->
                    @canany(['user.view', 'user.create', 'user.update'])
                        <div class="menu-item">
                            <a class="menu-link {{ request()->routeIs('users.*') ? 'active' : '' }}"
                                href="{{ route('users.index') }}" id="users_link">
                                <span class="menu-icon">
                                    <i class="ki-outline ki-user fs-2"></i>
                                </span>
                                <span class="menu-title">User Management</span>
                            </a>
                        </div>
                    @endcanany
                    <!--end::Users-->

                    <!--begin::Settings-->
                    @can('setting.view')
                        <div class="menu-item">
                            <a class="menu-link {{ request()->routeIs('settings.*', 'backup', 'employee-upload.index', 'payscale.index') ? 'active' : '' }}"
                                href="{{ route('settings.index') }}" id="settings_link">
                                <span class="menu-icon">
                                    <i class="ki-outline ki-setting-2 fs-2"></i>
                                </span>
                                <span class="menu-title">Settings</span>
                            </a>
                        </div>
                    @endcan
                    <!--end::Settings-->

                </div>
                <!--end::Menu-->
            </div>
        </div>
    </div>
    <!--end::Sidebar menu-->

    <!--begin::Footer-->
    <div class="app-sidebar-footer flex-column-auto pt-2 pb-6 px-6" id="kt_app_sidebar_footer">
        <a href="{{ route('logout') }}"
            onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
            class="btn btn-flex flex-center btn-custom btn-danger overflow-hidden text-nowrap px-0 h-40px w-100"
            data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-dismiss-="click" title="Click to logout">
            <span class="btn-label">Logout</span>
            <i class="ki-outline ki-document btn-icon fs-2 m-0"></i>
        </a>

        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
            @csrf
        </form>
    </div>
    <!--end::Footer-->

</div>

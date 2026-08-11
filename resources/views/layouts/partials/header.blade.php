<div id="kt_app_header" class="app-header " data-kt-sticky="true" data-kt-sticky-activate="{default: true, lg: true}"
    data-kt-sticky-name="app-header-minimize" data-kt-sticky-offset="{default: '200px', lg: '0'}"
    data-kt-sticky-animation="false">
    <!--begin::Header container-->
    <div class="app-container  container-fluid d-flex align-items-stretch justify-content-between "
        id="kt_app_header_container">
        <!--begin::Sidebar mobile toggle-->
        <div class="d-flex align-items-center d-lg-none ms-n3 me-1 me-md-2">
            <div class="btn btn-icon btn-active-color-primary w-35px h-35px" id="kt_app_sidebar_mobile_toggle">
                <i class="ki-outline ki-abstract-14 fs-2 fs-md-1"></i>
            </div>
        </div>
        <!--end::Sidebar mobile toggle-->

        <!--begin::Mobile logo-->
        <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
            <a href="{{ route('dashboard') }}" class="d-lg-none">
                <img alt="Logo" src="{{ asset('assets/img/icon.png') }}" class="h-30px" />
            </a>
        </div>
        <!--end::Mobile logo-->

        <!--begin::Header wrapper-->
        <div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1" id="kt_app_header_wrapper">

            <!--begin::Page title-->
            @yield('header-title')
            <!--end::Page title-->


            <!--begin::Navbar-->
            <div class="app-navbar flex-shrink-0">
                <!--begin::Role Name-->
                <div class="app-navbar-item ms-1 ms-md-4">
                    <span class="badge badge-lg badge-info fs-5">
                        {{ ucfirst(auth()->user()->getRoleNames()->first()) }}
                    </span>
                </div>
                <!--end::Role Name-->

                <!--begin::Notifications-->
                <div class="app-navbar-item ms-1 ms-md-4">
                    <!--begin::Menu wrapper-->
                    <div class="btn btn-icon btn-custom btn-icon-muted btn-active-light btn-active-color-primary w-35px h-35px position-relative"
                        data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-attach="parent"
                        data-kt-menu-placement="bottom-end" id="kt_menu_item_wow">
                        <i class="ki-outline ki-notification-status fs-2"></i>
                        @if (($headerUnreadCount ?? 0) > 0)
                            <span
                                class="position-absolute top-0 start-100 translate-middle badge badge-circle badge-danger fs-9">
                                {{ $headerUnreadCount > 9 ? '9+' : $headerUnreadCount }}
                            </span>
                        @endif
                    </div>
                    <!--begin::Menu-->
                    <div class="menu menu-sub menu-sub-dropdown menu-column w-350px w-lg-375px" data-kt-menu="true"
                        id="kt_menu_notifications">
                        <!--begin::Heading-->
                        <div class="d-flex flex-column bgi-no-repeat rounded-top"
                            style="background-image:url('{{ asset('assets/media/misc/menu-header-bg.jpg') }}')">
                            <!--begin::Title-->
                            <h3 class="text-white fw-semibold px-9 mt-10 mb-6">
                                Notifications
                                <span class="fs-8 opacity-75 ps-3">
                                    {{ ($headerUnreadCount ?? 0) > 0 ? $headerUnreadCount . ' unread' : 'All caught up' }}
                                </span>
                            </h3>
                            <!--end::Title-->
                        </div>
                        <!--end::Heading-->
                        <!--begin::Items-->
                        <div class="scroll-y mh-325px my-5 px-8">
                            @forelse (($headerNotifications ?? collect()) as $notification)
                                @php
                                    $data = $notification->data;
                                    $color = $data['color'] ?? 'primary';
                                    $icon = $data['icon'] ?? 'ki-notification-status';
                                    $isUnread = is_null($notification->read_at);
                                @endphp
                                <!--begin::Item-->
                                <a href="{{ route('notifications.read', $notification->id) }}"
                                    class="d-flex flex-stack py-4 px-3 rounded text-hover-primary {{ $isUnread ? 'bg-light-' . $color : '' }}">
                                    <!--begin::Section-->
                                    <div class="d-flex align-items-center">
                                        <!--begin::Symbol-->
                                        <div class="symbol symbol-35px me-4">
                                            <span class="symbol-label bg-light-{{ $color }}">
                                                <i
                                                    class="ki-outline {{ $icon }} fs-2 text-{{ $color }}"></i>
                                            </span>
                                        </div>
                                        <!--end::Symbol-->
                                        <!--begin::Title-->
                                        <div class="mb-0 me-2">
                                            <span class="fs-6 text-gray-800 fw-bold">
                                                {{ $data['title'] ?? 'Notification' }}
                                            </span>
                                            <div class="text-gray-500 fs-7">
                                                {{ \Illuminate\Support\Str::limit($data['message'] ?? '', 60) }}
                                            </div>
                                        </div>
                                        <!--end::Title-->
                                    </div>
                                    <!--end::Section-->
                                    <!--begin::Label-->
                                    <span class="badge badge-light fs-8 flex-shrink-0">
                                        {{ $notification->created_at->diffForHumans(['short' => true]) }}
                                    </span>
                                    <!--end::Label-->
                                </a>
                                <!--end::Item-->
                            @empty
                                <!--begin::Empty-->
                                <div class="text-center text-gray-500 py-10">
                                    <i class="ki-outline ki-notification-status fs-3x text-gray-300 mb-3"></i>
                                    <div class="fs-6">No notifications yet</div>
                                </div>
                                <!--end::Empty-->
                            @endforelse
                        </div>
                        <!--end::Items-->
                        <!--begin::View more-->
                        <div class="py-3 text-center border-top">
                            <a href="{{ route('notifications.index') }}"
                                class="btn btn-color-gray-600 btn-active-color-primary">
                                View All
                                <i class="ki-outline ki-arrow-right fs-5"></i>
                            </a>
                        </div>
                        <!--end::View more-->
                    </div>
                    <!--end::Menu-->
                    <!--end::Menu wrapper-->
                </div>
                <!--end::Notifications-->

                <!--begin::Clear Cache-->
                <div class="app-navbar-item ms-1 ms-md-4">
                    <a href="#" id="clear_cache_button" data-url="{{ route('clear.cache') }}"
                        data-bs-toggle="tooltip" data-bs-placement="bottom" title="Clear Cache"
                        class="btn btn-icon btn-custom btn-icon-muted btn-active-light btn-active-color-primary w-35px h-35px"><i
                            class="ki-outline ki-flash-circle fs-2"></i></a>
                </div>
                <!--end::Clear Cache-->

                <!--begin::Theme mode-->
                <div class="app-navbar-item ms-1 ms-md-4">
                    <!--begin::Menu toggle-->
                    <a href="#"
                        class="btn btn-icon btn-custom btn-icon-muted btn-active-light btn-active-color-primary w-35px h-35px"
                        data-kt-menu-trigger="{default:'click', lg: 'hover'}" data-kt-menu-attach="parent"
                        data-kt-menu-placement="bottom-end">
                        <i class="ki-outline ki-night-day theme-light-show fs-1"></i> <i
                            class="ki-outline ki-moon theme-dark-show fs-1"></i></a>
                    <!--begin::Menu toggle-->
                    <!--begin::Menu-->
                    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-title-gray-700 menu-icon-gray-500 menu-active-bg menu-state-color fw-semibold py-4 fs-base w-150px"
                        data-kt-menu="true" data-kt-element="theme-mode-menu">
                        <!--begin::Menu item-->
                        <div class="menu-item px-3 my-0">
                            <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="light">
                                <span class="menu-icon" data-kt-element="icon">
                                    <i class="ki-outline ki-night-day fs-2"></i> </span>
                                <span class="menu-title">
                                    Light
                                </span>
                            </a>
                        </div>
                        <!--end::Menu item-->
                        <!--begin::Menu item-->
                        <div class="menu-item px-3 my-0">
                            <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="dark">
                                <span class="menu-icon" data-kt-element="icon">
                                    <i class="ki-outline ki-moon fs-2"></i> </span>
                                <span class="menu-title">
                                    Dark
                                </span>
                            </a>
                        </div>
                        <!--end::Menu item-->
                        <!--begin::Menu item-->
                        <div class="menu-item px-3 my-0">
                            <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="system">
                                <span class="menu-icon" data-kt-element="icon">
                                    <i class="ki-outline ki-screen fs-2"></i> </span>
                                <span class="menu-title">
                                    System
                                </span>
                            </a>
                        </div>
                        <!--end::Menu item-->
                    </div>
                    <!--end::Menu-->
                </div>
                <!--end::Theme mode-->

                <!--begin::User menu-->
                <div class="app-navbar-item ms-1 ms-md-4" id="kt_header_user_menu_toggle">
                    <!--begin::Menu wrapper-->
                    <div class="cursor-pointer symbol symbol-35px"
                        data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-attach="parent"
                        data-kt-menu-placement="bottom-end">
                        <img src="{{ auth()->user()?->photo_url ? asset(auth()->user()->photo_url) : asset('assets/img/dummy.png') }}"
                            class="rounded-3" alt="user">

                    </div>

                    <!--begin::User account menu-->
                    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-300px"
                        data-kt-menu="true">
                        <!--begin::Menu item-->
                        <div class="menu-item px-3">
                            <div class="menu-content d-flex align-items-center px-3">
                                <!--begin::Avatar-->
                                <div class="symbol symbol-50px me-5">
                                    <img alt="Logo"
                                        src="{{ auth()->user()?->photo_url ? asset(auth()->user()->photo_url) : asset('assets/img/dummy.png') }}">

                                </div>
                                <!--end::Avatar-->
                                <!--begin::Username-->
                                <div class="d-flex flex-column">
                                    <div class="fw-bold d-flex align-items-center fs-5">
                                        {{ auth()->user()->name }}
                                    </div>
                                    <span class="fw-semibold text-muted fs-7 text-break">
                                        {{ auth()->user()->email }} </span>
                                </div>
                                <!--end::Username-->
                            </div>
                        </div>
                        <!--end::Menu item-->

                        <div class="separator my-2"></div>

                        <!--begin::Menu item-->
                        <div class="menu-item px-5">
                            <a href="{{ route('users.profile') }}" class="menu-link px-5">
                                My Profile
                            </a>
                        </div>
                        <!--end::Menu item-->
                        <!--begin::Menu separator-->
                        <div class="separator my-2"></div>
                        <!--end::Menu separator-->

                        <!--begin::Menu item-->
                        <div class="menu-item px-5">
                            <a href="{{ route('logout') }}" class="menu-link px-5"
                                onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                Sign Out
                            </a>
                        </div>
                        <!--end::Menu item-->
                    </div>
                    <!--end::User account menu-->

                    <!--end::Menu wrapper-->
                </div>
                <!--end::User menu-->
                <!--begin::Header menu toggle-->
                {{-- <div class="app-navbar-item d-lg-none ms-2 me-n2" title="Show header menu">
                    <div class="btn btn-flex btn-icon btn-active-color-primary w-30px h-30px"
                        id="kt_app_header_menu_toggle">
                        <i class="ki-outline ki-element-4 fs-1"></i>
                    </div>
                </div> --}}
                <!--end::Header menu toggle-->
                <!--begin::Aside toggle-->
                <!--end::Header menu toggle-->
            </div>
            <!--end::Navbar-->
        </div>
        <!--end::Header wrapper-->
    </div>
    <!--end::Header container-->
</div>

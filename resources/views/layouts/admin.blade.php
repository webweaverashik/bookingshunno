<!DOCTYPE html>
<html lang="en" direction="ltr" data-bs-theme="light" data-kt-app-layout="dark-sidebar"
      data-kt-app-header-fixed="true" data-kt-app-sidebar-enabled="true"
      data-kt-app-sidebar-fixed="true" data-kt-app-sidebar-hoverable="true"
      data-kt-app-sidebar-push-header="true" data-kt-app-sidebar-push-toolbar="true"
      data-kt-app-sidebar-push-footer="true">

<head>
    @include('layouts.partials.head')
</head>

<body id="kt_app_body" class="app-default">

<div class="d-flex flex-column flex-root app-root" id="kt_app_root">
    <div class="app-page flex-column flex-column-fluid" id="kt_app_page">

        @include('layouts.partials.header')

        <div class="app-wrapper flex-column flex-row-fluid" id="kt_app_wrapper">
            @include('layouts.partials.sidebar')

            <div class="app-main flex-column flex-row-fluid" id="kt_app_main">
                <div class="d-flex flex-column flex-column-fluid">
                    <div id="kt_app_content" class="app-content flex-column-fluid">
                        <div id="kt_app_content_container" class="app-container container-fluid">

                            @if ($errors->any())
                                <div class="alert alert-danger d-flex align-items-center p-5 mb-6">
                                    <i class="ki-outline ki-shield-cross fs-2hx text-danger me-4"></i>
                                    <div class="d-flex flex-column">
                                        <span class="fw-semibold">Please check the following:</span>
                                        <ul class="mb-0 mt-2">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            @endif

                            @yield('content')
                        </div>
                    </div>
                </div>

                @include('layouts.partials.footer')
            </div>
        </div>
    </div>
</div>

@include('layouts.partials.scripts')
</body>
</html>

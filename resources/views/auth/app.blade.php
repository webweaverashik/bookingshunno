<!DOCTYPE html>
<html lang="en">
<!--begin::Head-->
@include('layouts.partials.head')
<!--end::Head-->
<!--begin::Body-->

<body id="kt_body" class="app-blank bgi-size-cover bgi-attachment-fixed bgi-position-center">
    <!--begin::Theme mode setup on page load-->
    @include('layouts.partials.theme_mode')
    <!--end::Theme mode setup on page load-->
    <!--begin::Root-->
    <div class="d-flex flex-column flex-root" id="kt_app_root">
        <!--begin::Page bg image-->
        <style>
            body {
                background-image: url('{{ asset('assets/media/auth/bg10.jpeg') }}');
            }

            [data-bs-theme="dark"] body {
                background-image: url('{{ asset('assets/media/auth/bg10-dark.jpeg') }}');
            }
        </style>
        <!--end::Page bg image-->
        <!--begin::Authentication - Layout -->
        <div class="d-flex flex-column flex-lg-row flex-column-fluid">

            <div class="d-flex flex-column flex-lg-row-fluid min-h-200px min-h-lg-100">
                <div class="d-flex flex-column flex-center p-10 w-100 h-100">
                    <a href="{{ route('home') }}">
                        <img class="theme-light-show mx-auto mw-100 w-125px w-lg-300px mb-6 mb-lg-20"
                            src="{{ asset('assets/img/icon.png') }}" alt="BIDA CPF Logo" />
                        <img class="theme-dark-show mx-auto mw-100 w-125px w-lg-300px mb-6 mb-lg-20"
                            src="{{ asset('assets/img/icon.png') }}" alt="BIDA CPF Logo" />
                    </a>
                    <h1 class="text-gray-800 fs-2qx fw-bold text-center mb-0 mb-lg-13">BIDA CPF Management System</h1>
                    <div class="text-gray-600 fs-base text-center fw-semibold d-none d-lg-block">
                        A secure and centralized platform for managing Contributory Provident Fund (CPF) operations at
                        BIDA.<br>
                        The system automates CPF contributions, advance management, interest distribution, and ledger
                        maintenance while<br>
                        ensuring transparency, accuracy, and audit compliance. Empowering efficient CPF administration
                        through digital transformation.
                    </div>
                </div>
            </div>
            <div class="d-flex flex-column flex-lg-row-auto justify-content-center p-6 p-md-12">
                <div class="bg-body d-flex flex-column rounded-4 w-md-600px p-10 m-auto">
                    <div class="d-flex flex-column align-items-stretch w-350px w-md-400px m-auto">
                        <div class="py-5 py-lg-10">
                            @yield('content')
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Authentication - Layout-->
    </div>
    <!--end::Root-->
    <!--begin::Javascript-->
    <script>
        var hostUrl = "assets/";
    </script>
    <!--begin::Global Javascript Bundle(mandatory for all pages)-->
    <script src="{{ asset('assets/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('assets/js/scripts.bundle.js') }}"></script>
    <!--end::Global Javascript Bundle-->
    <!--begin::Custom Javascript(used for this page only)-->
    @stack('page-script')
    <!--end::Custom Javascript-->
    <!--end::Javascript-->

    @if (session('success') || session('warning') || session('error'))
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                toastr.options = {
                    "closeButton": true,
                    "debug": false,
                    "newestOnTop": true,
                    "progressBar": true,
                    "positionClass": "toastr-top-right",
                    "preventDuplicates": false,
                    "onclick": null,
                    "showDuration": "300",
                    "hideDuration": "1000",
                    "timeOut": "5000",
                    "extendedTimeOut": "1000",
                    "showEasing": "swing",
                    "hideEasing": "linear",
                    "showMethod": "fadeIn",
                    "hideMethod": "fadeOut"
                };

                @if (session('success'))
                    toastr.success("{{ session('success') }}");
                @endif

                @if (session('warning'))
                    toastr.warning("{{ session('warning') }}");
                @endif

                @if (session('error'))
                    toastr.error("{{ session('error') }}");
                @endif
            });
        </script>
    @endif
</body>
<!--end::Body-->

</html>

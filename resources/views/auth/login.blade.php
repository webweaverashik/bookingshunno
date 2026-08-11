@extends('auth.app')

@section('title', 'Login')

@section('content')
    <!--begin::Form-->
    <form class="form w-100" id="kt_sign_in_form" action="{{ route('login') }}" method="POST" novalidate>
        @csrf
        <!--begin::Heading-->
        <div class="text-center mb-11 mt-10 mt-md-0">
            <!--begin::Title-->
            <h1 class="text-gray-900 fw-bolder mb-3">Sign In</h1>
            <!--end::Title-->
            <!--begin::Subtitle-->
            {{-- <div class="text-gray-500 fw-semibold fs-6">Your Social Campaigns</div> --}}
            <!--end::Subtitle=-->
        </div>
        <!--begin::Heading-->

        <!--begin::Login options-->
        {{-- <div class="row g-3 mb-9">
            <!--begin::Col-->
            <div class="col-md-6">
                <!--begin::Google link=-->
                <a href="#"
                    class="btn btn-flex btn-outline btn-text-gray-700 btn-active-color-primary bg-state-light flex-center text-nowrap w-100">
                    <img alt="Logo" src="{{ asset('assets/media/svg/brand-logos/google-icon.svg') }}" class="h-15px me-3" />Sign in
                    with Google</a>
                <!--end::Google link=-->
            </div>
            <!--end::Col-->
            <!--begin::Col-->
            <div class="col-md-6">
                <!--begin::Google link=-->
                <a href="#"
                    class="btn btn-flex btn-outline btn-text-gray-700 btn-active-color-primary bg-state-light flex-center text-nowrap w-100">
                    <img alt="Logo" src="{{ asset('assets/media/svg/brand-logos/apple-black.svg') }}"
                        class="theme-light-show h-15px me-3" />
                    <img alt="Logo" src="{{ asset('assets/media/svg/brand-logos/apple-black-dark.svg') }}"
                        class="theme-dark-show h-15px me-3" />Sign in with Apple</a>
                <!--end::Google link=-->
            </div>
            <!--end::Col-->
        </div> --}}
        <!--end::Login options-->
        <!--begin::Separator-->
        {{-- <div class="separator separator-content my-14">
            <span class="w-125px text-gray-500 fw-semibold fs-7">Or with email</span>
        </div> --}}
        <!--end::Separator-->

        <!--begin::Input group=-->
        <div class="fv-row mb-5">
            <!--begin::Email-->
            <input type="email" placeholder="Your email address" name="email" autocomplete="off"
                class="form-control bg-transparent" required />

            <!--end::Email-->
        </div>
        <!--end::Input group=-->
        <div class="fv-row mb-3">
            <!--begin::Password-->
            <input type="password" placeholder="Your password" name="password" autocomplete="off"
                class="form-control bg-transparent" required />
            <!--end::Password-->
        </div>
        <!--end::Input group=-->
        <!--begin::Wrapper-->
        <div class="d-flex flex-stack flex-wrap gap-3 fs-base fw-semibold mb-8">
            <div></div>
            <!--begin::Link-->
            <a href="{{ route('password.request') }}" class="link-primary">Forgot your password?</a>
            <!--end::Link-->
        </div>
        <!--end::Wrapper-->
        <!--begin::Submit button-->
        <div class="d-grid">
            <button type="submit" class="btn btn-primary" id="kt_sign_in_submit">
                <!--begin::Indicator label-->
                <span class="indicator-label">Sign In</span>
                <!--end::Indicator label-->
                <!--begin::Indicator progress-->
                <span class="indicator-progress">Please wait...
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                <!--end::Indicator progress-->
            </button>
        </div>
        <!--end::Submit button-->
    </form>
    <!--end::Form-->
@endsection

@push('page-script')
    <script src="{{ asset('js/auth/login.js') }}"></script>
@endpush

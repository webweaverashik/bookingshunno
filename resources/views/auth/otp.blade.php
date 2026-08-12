@extends('auth.app')

@section('title', 'Verify Login')

@section('content')
    <!--begin::Form-->
    <form class="form w-100" id="kt_login_otp_form" action="{{ route('login.otp.verify') }}" method="POST"
        data-kt-resend-url="{{ route('login.otp.resend') }}" novalidate>
        @csrf

        <!--begin::Heading-->
        <div class="text-center mb-10">
            <h1 class="text-gray-900 fw-bolder mb-3">Two-Step Verification</h1>
            <div class="text-muted fw-semibold fs-6">
                Enter the verification code sent to
                <span class="fw-bold text-gray-700">{{ $maskedEmail }}</span>
            </div>
        </div>
        <!--end::Heading-->

        <!--begin::Section-->
        <div class="mb-10">
            <div class="fw-bold text-start text-gray-900 fs-6 mb-1 ms-1">Type your 6-digit code</div>

            <!--begin::Inputs-->
            <div class="d-flex flex-wrap flex-stack justify-content-center">
                <input type="text" inputmode="numeric" autocomplete="one-time-code"
                    class="form-control bg-transparent h-60px w-60px fs-2qx text-center border border-secondary rounded mx-1 my-2"
                    maxlength="1" data-otp-input="1" autofocus />
                <input type="text" inputmode="numeric"
                    class="form-control bg-transparent h-60px w-60px fs-2qx text-center border border-secondary rounded mx-1 my-2"
                    maxlength="1" data-otp-input="2" />
                <input type="text" inputmode="numeric"
                    class="form-control bg-transparent h-60px w-60px fs-2qx text-center border border-secondary rounded mx-1 my-2"
                    maxlength="1" data-otp-input="3" />
                <input type="text" inputmode="numeric"
                    class="form-control bg-transparent h-60px w-60px fs-2qx text-center border border-secondary rounded mx-1 my-2"
                    maxlength="1" data-otp-input="4" />
                <input type="text" inputmode="numeric"
                    class="form-control bg-transparent h-60px w-60px fs-2qx text-center border border-secondary rounded mx-1 my-2"
                    maxlength="1" data-otp-input="5" />
                <input type="text" inputmode="numeric"
                    class="form-control bg-transparent h-60px w-60px fs-2qx text-center border border-secondary rounded mx-1 my-2"
                    maxlength="1" data-otp-input="6" />
            </div>
            <!--end::Inputs-->

            <!--begin::Hidden combined value-->
            <input type="hidden" name="code" id="kt_login_otp_code" />
            <!--end::Hidden combined value-->
        </div>
        <!--end::Section-->

        <!--begin::Submit-->
        <div class="d-grid mb-6">
            <button type="submit" id="kt_login_otp_submit" class="btn btn-primary">
                <span class="indicator-label">Verify</span>
                <span class="indicator-progress">Please wait...
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
            </button>
        </div>
        <!--end::Submit-->

        <!--begin::Resend-->
        <div class="text-center fw-semibold fs-6">
            <span class="text-muted me-1">Didn't receive a code?</span>
            <a href="javascript:void(0);" class="link-primary" id="kt_login_otp_resend">Resend</a>
            <span class="text-muted ms-1 d-none" id="kt_login_otp_timer"></span>
        </div>
        <!--end::Resend-->

        <!--begin::Back-->
        <div class="text-center mt-5">
            <a href="{{ route('login') }}" class="text-gray-700 text-hover-primary fs-7">Back to sign in</a>
        </div>
        <!--end::Back-->
    </form>
    <!--end::Form-->
@endsection

@push('page-script')
    <script>
        var BidaOtpConfig = {
            length: {{ (int) config('otp.length', 6) }},
            resendAfter: {{ (int) config('otp.resend_after', 60) }}
        };
    </script>
    <script src="{{ asset('js/auth/otp.js') }}"></script>
@endpush

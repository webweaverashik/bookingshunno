{{--
    PHASE 15 — step two.

    A plain form POST, not AJAX. §14's AJAX rule covers admin CRUD; this is the
    public side, where the payment portal already posts and redirects. It also
    means the page works with JavaScript switched off or still loading, which
    matters more here than anywhere else in the app: somebody who cannot get
    past this screen cannot use the visitor area at all.

    The single hidden input is what actually submits. The row of boxes is a
    nicety layered on top by visitor.js, and if that script never runs the boxes
    stay usable as ordinary inputs — see the noscript fallback below.
--}}

@extends('layouts.public')

@section('title', 'Enter your code — Shunno Art Cafe')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/visitor.css') }}">
@endpush

@section('content')
    <section class="sh-band sh-band--cream sh-band--afterNav sh-authband">
        <div class="sh-wrap">
            <div class="sh-auth">

                <p class="sh-eyebrow">Check your inbox</p>
                <h1 class="sh-h2">Enter your code</h1>
                <p class="sh-lede">
                    If <strong>{{ $maskedEmail }}</strong> is on our records, a {{ $length }}-digit code is on
                    its way. It is good for {{ config('otp.expires_in', 5) }} minutes.
                </p>

                <x-visitor.flash />

                @error('code')
                    <p class="sh-flash sh-flash--bad" role="status">{{ $message }}</p>
                @enderror

                <form method="POST" action="{{ route('visitor.verify.submit') }}" class="sh-form" id="sh-otp-form"
                    data-length="{{ $length }}" novalidate>
                    @csrf

                    <input type="hidden" name="code" id="sh-otp-value">

                    {{-- inputmode="numeric" brings up the number pad on a phone,
                         which is where most of these are typed. --}}
                    <div class="sh-otp" role="group" aria-labelledby="sh-otp-label"
                        style="--sh-otp-count: {{ $length }}">
                        <span class="sh-otp__label" id="sh-otp-label">Verification code</span>
                        @for ($i = 0; $i < $length; $i++)
                            <input class="sh-otp__box" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1"
                                autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                                aria-label="Digit {{ $i + 1 }}" data-otp-box @if ($i === 0) autofocus @endif>
                        @endfor
                    </div>

                    <noscript class="sh-form__row">
                        {{-- With the enhancement off, the boxes above submit
                             nothing. This one does. --}}
                        <label class="form-label" for="sh-otp-plain">Verification code</label>
                        <input class="form-control" type="text" id="sh-otp-plain" name="code"
                            inputmode="numeric" maxlength="{{ $length }}" required>
                    </noscript>

                    <button class="sh-btn sh-btn--primary sh-form__go" type="submit">
                        Sign in
                        <span class="sh-btn__arrow" aria-hidden="true">&rarr;</span>
                    </button>
                </form>

                <div class="sh-auth__resend">
                    <form method="POST" action="{{ route('visitor.verify.resend') }}">
                        @csrf
                        <button class="sh-linkbtn" type="submit" id="sh-otp-resend"
                            data-wait="{{ $resendAfter }}">Send another code</button>
                    </form>
                    <span class="sh-auth__timer" id="sh-otp-timer" hidden></span>
                </div>

                <p class="sh-auth__aside">
                    Wrong address? <a href="{{ route('visitor.login') }}">Start again</a>.
                </p>

            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/visitor.js') }}" defer></script>
@endpush

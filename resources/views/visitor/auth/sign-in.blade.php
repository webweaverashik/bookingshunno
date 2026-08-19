{{--
    Step one of getting in.

    One field. There is no password field because there is no password: a
    visitor account is created by ReservationService::resolveVisitor() with a
    random string nobody has ever been sent. The copy says so plainly rather
    than leaving people hunting for a "forgot password" link that would not
    help them.
--}}

@extends('layouts.public')

@section('title', 'Sign in — Shunno Art Cafe')
@section('description', 'See your reservations, payment links and café credit at Shunno Art Cafe.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/visitor.css') }}">
@endpush

@section('content')
    {{-- sh-band--afterNav tells public.js this page has no hero, so the nav
         starts in its solid state instead of transparent over cream. --}}
    <section class="sh-band sh-band--cream sh-band--afterNav sh-authband">
        <div class="sh-wrap">
            <div class="sh-auth">

                <p class="sh-eyebrow">Returning visitor</p>
                <h1 class="sh-h2">Your visits</h1>
                <p class="sh-lede">
                    Enter the email address you booked with and we will send you a
                    {{ config('otp.length', 6) }}-digit code. No password — the address is the account.
                </p>

                <x-visitor.flash />

                <form method="POST" action="{{ route('visitor.login.send') }}" class="sh-form" novalidate>
                    @csrf

                    <div class="sh-form__row">
                        <label class="form-label" for="v-email">Email address</label>
                        <input class="form-control @error('email') is-invalid @enderror" type="email" id="v-email"
                            name="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required
                            autofocus>
                        @error('email')
                            <p class="invalid-feedback d-block">{{ $message }}</p>
                        @enderror
                    </div>

                    <button class="sh-btn sh-btn--primary sh-form__go" type="submit">
                        Send my code
                        <span class="sh-btn__arrow" aria-hidden="true">&rarr;</span>
                    </button>
                </form>

                <p class="sh-auth__aside">
                    Never booked with us before?
                    <a href="#" data-modal-open="sh-reserve">Request a visit</a> — an account is made for
                    you when you do.
                </p>

                {{-- Staff have a different door, and it wants a password. Linked
                     quietly rather than prominently: it is not what this page is
                     for, but a member of staff who lands here should not be
                     stuck. --}}
                <p class="sh-auth__staff">
                    Studio staff: <a href="{{ route('login') }}">sign in here</a>.
                </p>

            </div>
        </div>
    </section>
@endsection

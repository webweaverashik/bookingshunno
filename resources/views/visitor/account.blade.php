{{--
    PHASE 15 (corrected) — contact details.

    Three editable fields. The email address is shown but locked: it is the
    account, the only credential this side of the app has, and every
    notification about every booking has already gone to it. Moving it is a
    conversation with the studio, not a form field on a page anyone with a
    borrowed laptop can reach.

    FIELD STYLING. The form carries `sh-form`, which is what makes these inputs
    look like the ones in the reservation dialog. public.css defines the real
    field appearance under `.sh-modal .form-control`, and its global fallback
    sets a border and nothing else — no padding, no type scale — so a form
    outside either scope renders as the thin unstyled box this page was showing.
    See the note at the top of visitor.css.
--}}

@extends('layouts.public')

@section('title', 'Your details — Shunno Art Cafe')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/visitor.css') }}">
@endpush

@section('content')
    <section class="sh-band sh-band--cream sh-band--afterNav sh-authband">
        <div class="sh-wrap">
            <div class="sh-auth sh-auth--wide">

                <a class="sh-back" href="{{ route('visitor.index') }}">&larr; All your visits</a>

                <p class="sh-eyebrow">Your account</p>
                <h1 class="sh-h2">Your details</h1>
                <p class="sh-lede">
                    This is what we use to reach you about a booking. Keeping the number current
                    matters more than anything else here.
                </p>

                <x-visitor.flash />

                <form method="POST" action="{{ route('visitor.account.update') }}" class="sh-form" novalidate>
                    @csrf

                    <div class="sh-form__row">
                        <label class="form-label" for="v-name">Full name</label>
                        <input class="form-control @error('name') is-invalid @enderror" type="text" id="v-name"
                            name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required>
                        @error('name')
                            <p class="invalid-feedback d-block">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sh-form__row">
                        <label class="form-label" for="v-phone">Phone</label>
                        <input class="form-control @error('phone') is-invalid @enderror" type="tel" id="v-phone"
                            name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel"
                            placeholder="01XXXXXXXXX" required>
                        @error('phone')
                            <p class="invalid-feedback d-block">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sh-form__row">
                        <label class="form-label" for="v-whatsapp">
                            WhatsApp <span class="sh-optional">if different</span>
                        </label>
                        <input class="form-control @error('whatsapp') is-invalid @enderror" type="tel"
                            id="v-whatsapp" name="whatsapp" value="{{ old('whatsapp', $user->whatsapp) }}"
                            placeholder="01XXXXXXXXX">
                        @error('whatsapp')
                            <p class="invalid-feedback d-block">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sh-form__row">
                        <label class="form-label" for="v-email-locked">Email address</label>
                        {{-- disabled rather than readonly: it never submits, and
                             the muted dashed styling says "locked" before
                             somebody clicks it rather than after. --}}
                        <input class="form-control" type="email" id="v-email-locked" value="{{ $user->email }}"
                            disabled>
                        <p class="form-text">
                            This is how you sign in and where every reservation email goes, so it is not
                            editable here. Tell us and we will change it for you.
                        </p>
                    </div>

                    <button class="sh-btn sh-btn--primary sh-form__go" type="submit">Save changes</button>
                </form>

                <div class="sh-auth__signout">
                    <form method="POST" action="{{ route('visitor.logout') }}">
                        @csrf
                        <button class="sh-linkbtn" type="submit">Sign out</button>
                    </form>
                </div>

            </div>
        </div>
    </section>
@endsection

{{--
    The returning visitor's home.

    Ordered by what somebody actually came here for: what is next, then what is
    owed, then what they hold, then what has already happened. The archive is
    last because it is the least urgent thing on the page, however interesting
    it is to have.
--}}

@extends('layouts.public')

@section('title', 'Your visits — Shunno Art Cafe')
@section('description', 'Your reservations, payment links and café credit at Shunno Art Cafe.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/visitor.css') }}">
@endpush

@section('content')
    <section class="sh-band sh-band--sand sh-band--afterNav sh-visits__head">
        <div class="sh-wrap">
            <p class="sh-eyebrow">Your account</p>
            <h1 class="sh-h2">Hello, {{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first() }}</h1>

            <div class="sh-visits__stats">
                @if ($summary['visits'] > 0)
                    <span class="sh-stat">
                        <strong>{{ $summary['visits'] }}</strong>
                        {{ \Illuminate\Support\Str::plural('reservation', $summary['visits']) }} with us
                    </span>
                @endif

                @if ($summary['credit'] > 0)
                    <span class="sh-stat sh-stat--credit">
                        <strong>BDT {{ number_format($summary['credit']) }}</strong>
                        café credit to spend
                    </span>
                @endif
            </div>

            <div class="sh-visits__actions">
                <a class="sh-btn sh-btn--primary sh-btn--sm" href="#" data-modal-open="sh-reserve">
                    Reserve another visit
                    <span class="sh-btn__arrow" aria-hidden="true">&rarr;</span>
                </a>
                <a class="sh-btn sh-btn--ghost sh-btn--sm" href="{{ route('visitor.account') }}">Your details</a>

                {{-- Sign out belongs on this page, not only on the account page.
                     This is where somebody lands after signing in and where they
                     will be when they want to leave — burying the only way out
                     one click deeper, on a page about editing a phone number,
                     is how a shared laptop stays signed in. --}}
                <form class="sh-visits__out" method="POST" action="{{ route('visitor.logout') }}">
                    @csrf
                    <button class="sh-linkbtn" type="submit">Sign out</button>
                </form>
            </div>
        </div>
    </section>

    <section class="sh-band sh-band--cream">
        <div class="sh-wrap">

            <x-visitor.flash />

            {{-- ---------------------------------------------------------------
                 Coming up
                 --------------------------------------------------------------- --}}
            <div class="sh-head">
                <h2 class="sh-visits__h3">Coming up</h2>
            </div>

            @forelse ($upcoming as $reservation)
                @include('visitor.partials.visit-card', [
                    'reservation' => $reservation,
                    'next' => $portal->nextStep($reservation),
                ])
            @empty
                <div class="sh-empty">
                    <p>Nothing booked at the moment.</p>
                    <a class="sh-btn sh-btn--primary sh-btn--sm" href="#" data-modal-open="sh-reserve">
                        Reserve your visit
                        <span class="sh-btn__arrow" aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            @endforelse

            {{-- ---------------------------------------------------------------
                 Vouchers and café credit
                 ---------------------------------------------------------------
                 Both kinds in one list, distinguished by their own label, because
                 that is how VoucherType already frames the difference: where it
                 is spent. The line under each code says so in words, so nobody
                 arrives at the payment page holding café credit and expecting it
                 to pay for a workshop.
                 --------------------------------------------------------------- --}}
            @if ($vouchers->isNotEmpty())
                <div class="sh-head sh-head--spaced">
                    <h2 class="sh-visits__h3">Your codes</h2>
                </div>

                <div class="sh-vouchers">
                    @foreach ($vouchers as $voucher)
                        <div class="sh-voucher @if (!$voucher->isRedeemable()) is-spent @endif">
                            <div class="sh-voucher__top">
                                <span class="sh-voucher__kind">{{ $voucher->type->label() }}</span>
                                <span class="sh-chip sh-chip--{{ $voucher->displayColour() }}">
                                    {{ $voucher->displayStatus() }}
                                </span>
                            </div>

                            <p class="sh-voucher__code">{{ $voucher->code }}</p>
                            <p class="sh-voucher__value">BDT {{ number_format((float) $voucher->value) }}</p>

                            <p class="sh-voucher__note">
                                {{ $voucher->type->spendableOn() }}
                                @if ($reason = $voucher->unusableReason())
                                    <span class="sh-voucher__why">{{ $reason }}</span>
                                @elseif ($voucher->expires_at)
                                    <span class="sh-voucher__why">Good until
                                        {{ $voucher->expires_at->format('j F Y') }}.</span>
                                @endif
                            </p>

                            @if ($voucher->reservation)
                                <p class="sh-voucher__from">
                                    From your visit on {{ $voucher->reservation->reserved_date->format('j M Y') }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- ---------------------------------------------------------------
                 Everything else
                 --------------------------------------------------------------- --}}
            @if ($past->isNotEmpty())
                <div class="sh-head sh-head--spaced">
                    <h2 class="sh-visits__h3">Earlier</h2>
                </div>

                @foreach ($past as $reservation)
                    @include('visitor.partials.visit-card', [
                        'reservation' => $reservation,
                        'next' => $portal->nextStep($reservation),
                    ])
                @endforeach
            @endif

        </div>
    </section>
@endsection

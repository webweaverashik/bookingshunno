{{--
    One booking in full.

    The payment panel reproduces the brief's §10 summary exactly — total, type,
    required, paid, remaining — and takes every figure from the PAYMENT
    SNAPSHOT rather than from the live reservation. That is the point of the
    snapshot: this page should say the same thing next month as it does today,
    even if somebody corrects a party size in between. Reservation::payableTotal()
    is shown separately, as what is owed on the visit as a whole.

    Read-only throughout. There is no cancel button and no reschedule: both
    move money and capacity, and §8 puts both behind a person at the studio.
--}}

@extends('layouts.public')

@section('title', $reservation->title() . ' — ' . $reservation->reference_code)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/visitor.css') }}">
@endpush

@section('content')
    @php
        $start = \Carbon\CarbonImmutable::createFromTimeString($reservation->start_time);
        $end = $reservation->end_time ? \Carbon\CarbonImmutable::createFromTimeString($reservation->end_time) : null;
        $payment = $reservation->latestPayment();
    @endphp

    <section class="sh-band sh-band--sand sh-band--afterNav sh-detail__head">
        <div class="sh-wrap">
            <a class="sh-back" href="{{ route('visitor.index') }}">&larr; All your visits</a>

            <p class="sh-eyebrow">{{ $reservation->reference_code }}</p>
            <h1 class="sh-h2">{{ $reservation->title() }}</h1>

            <p class="sh-detail__when">
                {{ $reservation->reserved_date->format('l, j F Y') }}
                &middot;
                {{ $start->format('g:i A') }}@if ($end) &ndash; {{ $end->format('g:i A') }} @endif
            </p>

            <span class="sh-chip sh-chip--{{ $reservation->status->colour() }}">
                {{ $reservation->status->label() }}
            </span>
        </div>
    </section>

    <section class="sh-band sh-band--cream">
        <div class="sh-wrap sh-detail">

            <x-visitor.flash />

            {{-- What happens next. Deliberately the first thing on the page. --}}
            <div class="sh-panel @if ($next['owed']) sh-panel--owed @endif">
                <h2 class="sh-panel__title">{{ $next['title'] }}</h2>
                <p class="sh-panel__body">{{ $next['body'] }}</p>

                @if ($next['cta'])
                    <a class="sh-btn sh-btn--primary sh-btn--sm" href="{{ $next['cta']['url'] }}">
                        {{ $next['cta']['label'] }}
                        <span class="sh-btn__arrow" aria-hidden="true">&rarr;</span>
                    </a>
                @endif
            </div>

            {{-- ---------------------------------------------------------------
                 The booking
                 --------------------------------------------------------------- --}}
            <div class="sh-panel">
                <h2 class="sh-panel__title">Your booking</h2>

                <dl class="sh-kv">
                    <div>
                        <dt>Experience</dt>
                        <dd>{{ $reservation->title() }}</dd>
                    </div>
                    <div>
                        <dt>Date</dt>
                        <dd>{{ $reservation->reserved_date->format('l, j F Y') }}</dd>
                    </div>
                    <div>
                        <dt>Time</dt>
                        <dd>{{ $start->format('g:i A') }}@if ($end) to {{ $end->format('g:i A') }} @endif</dd>
                    </div>
                    <div>
                        <dt>Party</dt>
                        <dd>{{ $reservation->participants }}
                            {{ \Illuminate\Support\Str::plural('person', $reservation->participants) }}</dd>
                    </div>

                    @if ($reservation->purposes->isNotEmpty())
                        <div>
                            <dt>Coming for</dt>
                            <dd>{{ $reservation->purposes->pluck('name')->join(', ', ' and ') }}</dd>
                        </div>
                    @endif

                    @if ($reservation->special_requests)
                        <div class="sh-kv__wide">
                            <dt>What you told us</dt>
                            <dd>{{ $reservation->special_requests }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- ---------------------------------------------------------------
                 Money
                 ---------------------------------------------------------------
                 Shown only once there is a payment request. Before that there is
                 no figure the visitor has been asked for, and putting a price on
                 an unreviewed request would read as a bill.
                 --------------------------------------------------------------- --}}
            @if ($payment)
                <div class="sh-panel">
                    <h2 class="sh-panel__title">Payment</h2>

                    <dl class="sh-kv sh-kv--money">
                        <div>
                            <dt>Reservation total</dt>
                            <dd>BDT {{ number_format((float) $payment->reservation_total) }}</dd>
                        </div>
                        <div>
                            <dt>Payment type</dt>
                            <dd>{{ $payment->type->label() }} ({{ $payment->percentage }}%)</dd>
                        </div>
                        <div>
                            <dt>Payment required</dt>
                            <dd>BDT {{ number_format((float) $payment->amount_due) }}</dd>
                        </div>
                        <div>
                            <dt>Amount paid</dt>
                            <dd>BDT {{ number_format((float) $payment->amount_paid) }}</dd>
                        </div>
                        <div class="sh-kv__strong">
                            <dt>Remaining</dt>
                            <dd>BDT {{ number_format($payment->remainingOnReservation()) }}</dd>
                        </div>

                        @if ($payment->isOpen() && $payment->due_at)
                            <div>
                                <dt>Please pay by</dt>
                                <dd>{{ $payment->due_at->format('j F Y, g:i A') }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if ($payment->isOpen())
                        <a class="sh-btn sh-btn--primary sh-btn--sm"
                            href="{{ route('payment.portal', $payment->token) }}">
                            Open the payment page
                            <span class="sh-btn__arrow" aria-hidden="true">&rarr;</span>
                        </a>
                    @endif

                    {{-- Receipts. Every settlement gets one, online or taken at
                         the counter, and the payslip route is the same document
                         staff can reprint. --}}
                    @if ($payment->receipts()->isNotEmpty())
                        <h3 class="sh-panel__sub">Receipts</h3>
                        <ul class="sh-receipts">
                            @foreach ($payment->receipts() as $transaction)
                                <li>
                                    <a href="{{ route('payslip', [$payment->token, $transaction->reference]) }}">
                                        <span class="sh-receipts__ref">{{ $transaction->reference }}</span>
                                        <span class="sh-receipts__amt">BDT
                                            {{ number_format((float) $transaction->amount) }}</span>
                                        <span
                                            class="sh-receipts__on">{{ $transaction->received_at?->format('j M Y') }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            {{-- ---------------------------------------------------------------
                 Café credit earned by this visit
                 --------------------------------------------------------------- --}}
            @if ($vouchers->isNotEmpty())
                <div class="sh-panel">
                    <h2 class="sh-panel__title">From this visit</h2>

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
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <p class="sh-detail__foot">
                Something not right? Reply to any of our emails, or message us on WhatsApp, quoting
                <strong>{{ $reservation->reference_code }}</strong>.
            </p>

        </div>
    </section>
@endsection

{{--
    One booking, as a visitor sees it.

    @param App\Models\Reservation\Reservation $reservation
    @param array                  $next        from VisitorPortalService::nextStep()

    The chip colour comes straight from ReservationStatus::colour(), the same
    method the admin badges use. One mapping, so a status can never be amber in
    the panel and green here.

    No money is worked out in this file. payableTotal() and outstandingTotal()
    are model methods reading columns the service layer wrote — nothing is
    added up in Blade, per the standing rule.
--}}

@php
    $start = \Carbon\CarbonImmutable::createFromTimeString($reservation->start_time);
    $end = $reservation->end_time ? \Carbon\CarbonImmutable::createFromTimeString($reservation->end_time) : null;
@endphp

<article class="sh-visit @if ($next['owed']) sh-visit--owed @endif">

    <div class="sh-visit__when">
        <span class="sh-visit__day">{{ $reservation->reserved_date->format('j') }}</span>
        <span class="sh-visit__month">{{ $reservation->reserved_date->format('M') }}</span>
        <span class="sh-visit__year">{{ $reservation->reserved_date->format('Y') }}</span>
    </div>

    <div class="sh-visit__body">
        <div class="sh-visit__top">
            <h3 class="sh-visit__title">{{ $reservation->title() }}</h3>
            <span class="sh-chip sh-chip--{{ $reservation->status->colour() }}">
                {{ $reservation->status->label() }}
            </span>
        </div>

        <p class="sh-visit__meta">
            {{ $start->format('g:i A') }}@if ($end) &ndash; {{ $end->format('g:i A') }} @endif
            &middot;
            {{ $reservation->participants }} {{ \Illuminate\Support\Str::plural('person', $reservation->participants) }}
            &middot;
            <span class="sh-visit__ref">{{ $reservation->reference_code }}</span>
        </p>

        <p class="sh-visit__next">
            <strong>{{ $next['title'] }}</strong>
            <span>{{ $next['body'] }}</span>
        </p>

        <div class="sh-visit__foot">
            @if ($next['cta'])
                <a class="sh-btn sh-btn--primary sh-btn--sm" href="{{ $next['cta']['url'] }}">
                    {{ $next['cta']['label'] }}
                    <span class="sh-btn__arrow" aria-hidden="true">&rarr;</span>
                </a>
            @endif

            <a class="sh-visit__link" href="{{ route('visitor.show', $reservation) }}">
                See the details
            </a>

            @if ($reservation->payableTotal() > 0)
                <span class="sh-visit__money">
                    BDT {{ number_format($reservation->payableTotal()) }}
                    @if ($reservation->outstandingTotal() > 0.01 && $reservation->amountPaid() > 0)
                        <small>BDT {{ number_format($reservation->outstandingTotal()) }} still to pay</small>
                    @elseif ($reservation->isFullyPaid())
                        <small>Paid in full</small>
                    @endif
                </span>
            @endif
        </div>
    </div>
</article>

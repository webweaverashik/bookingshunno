{{--
    The visit, as a table, in every visitor-facing email.

    One partial rather than the same six lines repeated: when the client asks
    for the date format to change, or for the session length to appear, it
    changes once and every email stays consistent with the others.

    Deliberately no money row here — what is owed differs by email. The received
    and approved templates state it themselves, and the declined and cancelled
    ones should not mention a figure at all.
--}}

@php
    $item = $reservation->items->first();
    $start = \Carbon\CarbonImmutable::createFromTimeString($reservation->start_time);
    $end = \Carbon\CarbonImmutable::createFromTimeString($reservation->end_time);
@endphp

<table width="100%" cellpadding="0" cellspacing="0" style="margin: 16px 0;">
    <tr>
        <td style="padding: 6px 0; color: #6b6660;">Reference</td>
        <td style="padding: 6px 0; text-align: right; font-weight: bold;">{{ $reservation->reference_code }}</td>
    </tr>
    <tr>
        <td style="padding: 6px 0; color: #6b6660;">Session</td>
        <td style="padding: 6px 0; text-align: right;">{{ $item?->title_snapshot ?? 'Visit' }}</td>
    </tr>
    <tr>
        <td style="padding: 6px 0; color: #6b6660;">Date</td>
        <td style="padding: 6px 0; text-align: right;">{{ $reservation->reserved_date->format('l, j F Y') }}</td>
    </tr>
    <tr>
        <td style="padding: 6px 0; color: #6b6660;">Time</td>
        <td style="padding: 6px 0; text-align: right;">
            {{ $start->format('g:i A') }} &ndash; {{ $end->format('g:i A') }}
        </td>
    </tr>
    <tr>
        <td style="padding: 6px 0; color: #6b6660;">People</td>
        <td style="padding: 6px 0; text-align: right;">{{ $reservation->participants }}</td>
    </tr>
</table>

{{--
    <option> list for the admin edit form. Used both when the form is first
    rendered and when the date changes, so the two can never drift apart.

    Unavailable slots are kept and labelled with the reason rather than removed:
    an admin who can see "6:00 PM — fully booked" knows what the override would
    be overriding. They are NOT disabled here, unlike on the public form —
    picking one is exactly what an Admin with the override might mean to do, and
    the validator refuses it without the tick regardless.
--}}

@forelse ($slots as $slot)
    <option value="{{ $slot['value'] }}" @selected($slot['value'] === $selected)>
        @if (!$slot['available'])
            {{ $slot['label'] }} &mdash; {{ $slot['reason'] }}
        @elseif ($slot['note'])
            {{ $slot['label'] }} &middot; {{ $slot['note'] }}
        @else
            {{ $slot['label'] }}
        @endif
    </option>
@empty
    {{-- Either the studio is closed that day or the session no longer fits in
         the remaining window. Keeping the current time as the only option means
         saving an unrelated change does not silently blank the start time. --}}
    <option value="{{ $selected }}" selected>{{ $selected }} &mdash; no slots on that date</option>
@endforelse

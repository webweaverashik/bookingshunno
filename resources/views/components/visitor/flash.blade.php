{{--
    The two flash keys the visitor area uses.

    Named visitor_notice / visitor_error rather than success / error because the
    public layout is shared with the landing page, and the reservation popup
    already puts its own messages through session()->flash. Distinct keys mean a
    stale message from one cannot surface on the other.

    role="status" rather than "alert": these are confirmations and gentle
    corrections, not emergencies, and an assertive live region interrupts a
    screen reader mid-sentence to say "a new code is on its way".
--}}

@if (session('visitor_notice'))
    <p class="sh-flash sh-flash--ok" role="status">{{ session('visitor_notice') }}</p>
@endif

@if (session('visitor_error'))
    <p class="sh-flash sh-flash--bad" role="status">{{ session('visitor_error') }}</p>
@endif

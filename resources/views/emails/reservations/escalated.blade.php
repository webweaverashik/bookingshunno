{{--
    INTERNAL. Goes to every active Admin, never to the visitor.

    A Manager cannot approve, so this is how a decision reaches someone who can.
    The note is what the Manager needs decided; the visitor's own words are here
    too, because an Admin who has not spoken to them should not have to open the
    panel to understand the request.

    Nothing here is written from the visitor's perspective — it is
    studio-to-studio, and the tone should stay that way.
--}}

@component('mail::message')
# A reservation needs your decision

{{ $reservation->user?->name ?? 'A visitor' }} has a request that a Manager
cannot approve.

@component('mail::panel')
{{ $note }}
@endcomponent

@include('emails.partials.reservation-summary', ['reservation' => $reservation])

**Visitor:** {{ $reservation->user?->name }} &middot; {{ $reservation->user?->email }}@if ($reservation->user?->phone) &middot; {{ $reservation->user->phone }}@endif

**Total as it stands:** BDT {{ number_format($reservation->payableTotal()) }}@if ($reservation->hasManualPrice()) (agreed price — {{ $reservation->total_override_reason }})@endif

@if ($reservation->special_requests)
**What the visitor wrote:** {{ $reservation->special_requests }}
@endif

@component('mail::button', ['url' => route('admin.reservations.index', ['q' => $reservation->reference_code, 'status' => 'all', 'range' => 'all'])])
Open in the admin panel
@endcomponent

@component('mail::subcopy')
You are receiving this because you hold the Admin role. The visitor has not been
told anything — they are still waiting.
@endcomponent
@endcomponent

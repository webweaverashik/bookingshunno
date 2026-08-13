@php
    use App\Models\Workshop;
    use App\Support\SessionSlots;
    use App\Support\VisitPurposes;

    // PHASE 6: the menu is read from the database. Workshop::menu() is cached,
    // and the landing page has already warmed it by the time this partial runs.
    $experiences = Workshop::menu();

    // Built here rather than inline below: Blade cannot balance a multi-line
    // array inside a directive's parentheses and emits broken PHP.
$reserveConfig = [
    'endpoint' => route('reservation.request.store'),
    'slots' => SessionSlots::byExperience(),
    'closedDays' => array_values(config('shunno.operating.closed_days')),
    'discount' => [
        'min' => (int) config('shunno.group_discount.min_participants'),
        'percent' => (int) config('shunno.group_discount.percentage'),
        ],
    ];
@endphp

<div class="modal fade sh-modal" id="sh-reserve" tabindex="-1" aria-labelledby="sh-reserve-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <div>
                    <p class="sh-eyebrow">Reserve your visit</p>
                    <h2 class="sh-modal__title" id="sh-reserve-title">Tell us about your visit</h2>
                </div>
                <button type="button" class="btn-close" data-modal-dismiss aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <p class="sh-modal__intro">
                    This is a request, not a booking &mdash; nothing is charged now. A person reads
                    every request and replies by email, usually within a day. Payment is only asked
                    for once your visit has been approved.
                </p>

                {{-- Errors the field-level messages cannot express (network, throttling, 500). --}}
                <div class="sh-formalert" id="sh-form-alert" role="alert" hidden></div>

                <form id="sh-reserve-form" novalidate>
                    @csrf

                    {{-- Honeypot. Hidden from people, irresistible to bots. --}}
                    <div class="sh-hp" aria-hidden="true">
                        <label for="sh-website">Leave this field empty</label>
                        <input type="text" id="sh-website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <fieldset class="sh-fieldset">
                        <legend>What would you like to do?</legend>

                        <div class="sh-choices" role="radiogroup" aria-describedby="sh-experience-error">
                            @forelse ($experiences as $experience)
                                <label class="sh-choice">
                                    <input type="radio" name="experience" value="{{ $experience->slug }}"
                                        data-price="{{ $experience->price }}"
                                        data-minutes="{{ $experience->duration_minutes }}"
                                        data-max="{{ $experience->max_participants }}"
                                        @checked($loop->first)>
                                    <span class="sh-choice__body">
                                        <span class="sh-choice__title">{{ $experience->title }}</span>
                                        <span class="sh-choice__meta">{{ $experience->medium }}</span>
                                    </span>
                                    <span class="sh-choice__price">
                                        {{ number_format((float) $experience->price) }}
                                        <small>BDT &middot; {{ $experience->durationLabel() }}</small>
                                    </span>
                                </label>
                            @empty
                                <p class="sh-choice__empty">
                                    No sessions are open for reservation at the moment. Please message us
                                    on WhatsApp and we'll help directly.
                                </p>
                            @endforelse
                        </div>
                        <p class="invalid-feedback d-block" id="sh-experience-error" hidden></p>
                    </fieldset>

                    <fieldset class="sh-fieldset">
                        <legend>When?</legend>

                        <div class="sh-grid2">
                            <div>
                                <label class="form-label" for="sh-date">Preferred date</label>
                                <input class="form-control" type="date" id="sh-date" name="date"
                                    min="{{ now()->toDateString() }}" required
                                    aria-describedby="sh-date-help sh-date-error">
                                <p class="form-text" id="sh-date-help">Monday to Saturday. We're closed on Sundays.</p>
                                <p class="invalid-feedback" id="sh-date-error"></p>
                            </div>

                            <div>
                                <label class="form-label" for="sh-time">Preferred start time</label>
                                <select class="form-select" id="sh-time" name="time" required
                                    aria-describedby="sh-time-help sh-time-error"></select>
                                <p class="form-text" id="sh-time-help">Times shown are the ones long enough for the
                                    session you picked.</p>
                                <p class="invalid-feedback" id="sh-time-error"></p>
                            </div>

                            <div>
                                <label class="form-label" for="sh-participants">How many people?</label>
                                <input class="form-control" type="number" id="sh-participants" name="participants"
                                    value="1" min="1" max="30" inputmode="numeric" required
                                    aria-describedby="sh-participants-help sh-participants-error">
                                <p class="form-text" id="sh-participants-help">
                                    {{ config('shunno.group_discount.min_participants') }} or more gets
                                    {{ config('shunno.group_discount.percentage') }}% off.
                                </p>
                                <p class="invalid-feedback" id="sh-participants-error"></p>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="sh-fieldset">
                        <legend>What brings you? <span class="sh-optional">Optional &middot; choose any</span></legend>

                        <div class="sh-tickets">
                            @foreach (VisitPurposes::all() as $key => $label)
                                <label class="sh-ticket">
                                    <input type="checkbox" name="purposes[]" value="{{ $key }}">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset class="sh-fieldset">
                        <legend>About you</legend>

                        <div class="sh-grid2">
                            <div>
                                <label class="form-label" for="sh-name">Full name</label>
                                <input class="form-control" type="text" id="sh-name" name="name"
                                    autocomplete="name" required aria-describedby="sh-name-error">
                                <p class="invalid-feedback" id="sh-name-error"></p>
                            </div>

                            <div>
                                <label class="form-label" for="sh-email">Email</label>
                                <input class="form-control" type="email" id="sh-email" name="email"
                                    autocomplete="email" required aria-describedby="sh-email-help sh-email-error">
                                <p class="form-text" id="sh-email-help">This is where your reply and payment link go.
                                </p>
                                <p class="invalid-feedback" id="sh-email-error"></p>
                            </div>

                            <div>
                                <label class="form-label" for="sh-phone">Phone or WhatsApp</label>
                                <input class="form-control" type="tel" id="sh-phone" name="phone"
                                    autocomplete="tel" placeholder="01XXXXXXXXX" required
                                    aria-describedby="sh-phone-error">
                                <p class="invalid-feedback" id="sh-phone-error"></p>
                            </div>
                        </div>

                        <div class="sh-field">
                            <label class="form-label" for="sh-notes">Anything we should know? <span
                                    class="sh-optional">Optional</span></label>
                            <textarea class="form-control" id="sh-notes" name="notes" rows="3" maxlength="1000"
                                placeholder="Accessibility needs, a birthday, a group of school students, a session you'd like shaped a particular way…"
                                aria-describedby="sh-notes-error"></textarea>
                            <p class="invalid-feedback" id="sh-notes-error"></p>
                        </div>

                        <div class="sh-field form-check sh-consent">
                            <input class="form-check-input" type="checkbox" id="sh-consent" name="consent"
                                value="1" required aria-describedby="sh-consent-error">
                            <label class="form-check-label" for="sh-consent">
                                I understand this is a request, and that my visit is confirmed only after
                                Shunno approves it and payment is made.
                            </label>
                            <p class="invalid-feedback" id="sh-consent-error"></p>
                        </div>
                    </fieldset>
                </form>

                {{-- Success state replaces the form in place. --}}
                <div class="sh-done" id="sh-reserve-done" hidden tabindex="-1">
                    <div class="sh-done__mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6L9 17l-5-5" />
                        </svg>
                    </div>
                    <h3>Request received</h3>
                    <p class="sh-done__ref">Your reference is <b id="sh-done-ref"></b></p>
                    <p id="sh-done-summary"></p>
                    <p class="sh-done__next">
                        We'll email you once a person has reviewed it. Nothing has been charged, and
                        your date isn't held until the request is approved.
                    </p>
                    <button type="button" class="sh-btn sh-btn--ghost" data-modal-dismiss>Close</button>
                </div>
            </div>

            <div class="modal-footer sh-modal__foot" id="sh-reserve-foot">
                <div class="sh-total" aria-live="polite">
                    <span class="sh-total__row"><span>Subtotal</span><b id="sh-sum-subtotal">—</b></span>
                    <span class="sh-total__row sh-total__row--discount" id="sh-sum-discount-row" hidden>
                        <span>Group discount</span><b id="sh-sum-discount">—</b>
                    </span>
                    <span class="sh-total__row sh-total__row--total"><span>Estimated total</span><b
                            id="sh-sum-total">—</b></span>
                    <span class="sh-total__note">Payable only after approval</span>
                </div>

                <button class="sh-btn sh-btn--primary" type="submit" form="sh-reserve-form" id="sh-submit">
                    <span class="sh-btn__label">Send request</span>
                    <span class="sh-btn__arrow" aria-hidden="true">&rarr;</span>
                    <span class="sh-spinner" aria-hidden="true" hidden></span>
                </button>
            </div>

        </div>
    </div>
</div>

{{-- Server-generated config the popup needs. Slots are derived per session
     duration so a 4-hour booking can never be offered an 8pm start.
     JSON_HEX_TAG stops a stray "</script>" from ever breaking out. --}}
<script type="application/json" id="sh-reserve-config">
    {!! json_encode($reserveConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
</script>

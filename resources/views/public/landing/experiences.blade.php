<section class="sh-band sh-band--cream" id="experiences">
    <div class="sh-wrap">
        <x-public.section-heading
            eyebrow="Experiences"
            title="Choose by the time you have"
            lede="Every session is built around a length, not a level. Start with an hour in the cafe or give a whole evening to one piece — the making adjusts to you either way."
        />

        {{-- The printed menu is organised by session length, so the page is too. --}}
        <div class="sh-axis" aria-hidden="true">
            <span>1 hour</span><div class="sh-axis__rail"></div><span>4 hours</span>
        </div>

        <div class="sh-grid">
            @foreach($experiences as $experience)
                <x-public.experience-card :experience="$experience" />
            @endforeach

            <article class="sh-card sh-card--alt">
                <div class="sh-card__body">
                    <h3 class="sh-card__title">Bringing a group?</h3>
                    <p class="sh-card__medium">Four or more people</p>
                    <p class="sh-card__desc">
                        Reserve any session for four or more and a
                        {{ config('shunno.group_discount.percentage') }}% discount is applied to the
                        whole booking. Tell us the number when you request your visit.
                    </p>
                    <div class="sh-card__foot">
                        <span class="sh-card__save">{{ config('shunno.group_discount.percentage') }}% off</span>
                    </div>
                </div>
            </article>

            <article class="sh-card sh-card--alt">
                <div class="sh-card__body">
                    <h3 class="sh-card__title">Something else in mind?</h3>
                    <p class="sh-card__medium">Custom sessions</p>
                    <p class="sh-card__desc">
                        Sessions can be shaped around a school group, a team, a birthday or a longer
                        project. Describe what you're after and we'll come back with a plan.
                    </p>
                    <div class="sh-card__foot">
                        <a class="sh-card__link" href="{{ route('reservation.info') }}">Start a request &rarr;</a>
                    </div>
                </div>
            </article>
        </div>

        <p class="sh-note">
            <strong>Rates are per person and include all materials.</strong>
            No experience is needed for any session.
        </p>
    </div>
</section>

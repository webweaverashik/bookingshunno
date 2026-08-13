{{--
    PHASE 6: $experience is an App\Models\Workshop, not the array
    ExperienceCatalogue used to hand over. The prop name is unchanged so the
    experiences section did not have to move.
--}}
@props(['experience'])

@php
    // The duration meter is four ticks, one per hour: the longest session on the
    // printed menu is 4 hours. A longer one simply fills the bar.
    $ticks = $experience->durationTicks();
    $image = $experience->imageUrl();
@endphp

<article class="sh-card">
    @if($image)
        <div class="sh-card__media">
            <img src="{{ $image }}" alt="" loading="lazy" decoding="async">
            <span class="sh-card__tag">{{ $experience->categoryLabel() }}</span>
        </div>
    @endif

    <div class="sh-card__body">
        <h3 class="sh-card__title">{{ $experience->title }}</h3>

        @if($experience->medium)
            <p class="sh-card__medium">{{ $experience->medium }}</p>
        @endif

        <p class="sh-card__desc">{{ $experience->short_description ?: $experience->description }}</p>

        <div class="sh-card__foot">
            <span class="sh-card__price">
                {{ number_format((float) $experience->price) }}<small>BDT</small>
            </span>
            <span class="sh-dur">
                {{ $experience->durationLabel() }}
                <span class="sh-meter" aria-hidden="true">
                    @for($i = 1; $i <= 4; $i++)
                        <i @class(['is-on' => $i <= $ticks])></i>
                    @endfor
                </span>
            </span>
        </div>
    </div>
</article>

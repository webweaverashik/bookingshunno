@props(['experience'])

@php
    // The duration meter is four ticks, one per hour: the longest session is 4 hours.
    $hours = (int) data_get($experience, 'hours');
    $image = data_get($experience, 'image');
@endphp

<article class="sh-card">
    @if($image)
        <div class="sh-card__media">
            <img src="{{ asset($image) }}" alt="" loading="lazy" decoding="async">
            <span class="sh-card__tag">{{ data_get($experience, 'category') }}</span>
        </div>
    @endif

    <div class="sh-card__body">
        <h3 class="sh-card__title">{{ data_get($experience, 'title') }}</h3>

        @if($medium = data_get($experience, 'medium'))
            <p class="sh-card__medium">{{ $medium }}</p>
        @endif

        <p class="sh-card__desc">{{ data_get($experience, 'description') }}</p>

        <div class="sh-card__foot">
            <span class="sh-card__price">
                {{ number_format((float) data_get($experience, 'price')) }}<small>BDT</small>
            </span>
            <span class="sh-dur">
                {{ $hours }} {{ \Illuminate\Support\Str::plural('hr', $hours) }}
                <span class="sh-meter" aria-hidden="true">
                    @for($i = 1; $i <= 4; $i++)
                        <i @class(['is-on' => $i <= $hours])></i>
                    @endfor
                </span>
            </span>
        </div>
    </div>
</article>

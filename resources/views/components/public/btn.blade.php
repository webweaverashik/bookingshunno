@props([
    'href'    => '#',
    'variant' => 'primary',   // primary | ghost | onDark
    'arrow'   => false,
])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'sh-btn sh-btn--' . $variant]) }}>
    {{ $slot }}
    @if($arrow)
        <span class="sh-btn__arrow" aria-hidden="true">&rarr;</span>
    @endif
</a>

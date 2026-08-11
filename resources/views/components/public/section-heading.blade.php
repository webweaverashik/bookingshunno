@props([
    'eyebrow' => null,
    'title'   => null,
    'lede'    => null,
])

<div class="sh-head">
    @if($eyebrow)<p class="sh-eyebrow">{{ $eyebrow }}</p>@endif
    @if($title)<h2 class="sh-h2">{!! $title !!}</h2>@endif
    @if($lede)<p class="sh-lede">{{ $lede }}</p>@endif
    {{ $slot }}
</div>

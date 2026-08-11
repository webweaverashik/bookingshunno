{{-- Transparent over the hero, solid cream once scrolled (see public.js). --}}
<header class="sh-nav" id="sh-nav">
    <div class="sh-wrap sh-nav__inner">
        <a class="sh-nav__brand" href="{{ route('home') }}">
            <img class="sh-nav__mark" src="{{ asset('img/shunno-logo.png') }}" alt="" width="40" height="40">
            <span class="sh-nav__name">Shunno<span>Art Cafe</span></span>
        </a>

        <button class="sh-nav__toggle" type="button" aria-expanded="false" aria-controls="sh-menu" aria-label="Open menu">
            <span></span><span></span><span></span>
        </button>

        <ul class="sh-nav__links" id="sh-menu">
            <li><a href="{{ route('home') }}#experiences">Experiences</a></li>
            <li><a href="{{ route('home') }}#studio">The studio</a></li>
            <li><a href="{{ route('home') }}#how">How it works</a></li>
            <li><a href="{{ route('home') }}#contact">Visit</a></li>
            <li class="sh-nav__cta">
                <x-public.btn :href="route('reservation.info')" variant="onDark">Reserve your visit</x-public.btn>
            </li>
        </ul>
    </div>
</header>

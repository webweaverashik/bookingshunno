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

            {{--
                The way back in.

                One item that says the same thing in all three states, because
                "Your visits" is what somebody is looking for whether or not
                they happen to be signed in at that moment; only the destination
                changes. Staff get sent to the panel instead: they are not
                visitors, and EnsureVisitor would bounce them anyway.
            --}}
            @auth
                @if (auth()->user()->isStaff())
                    <li><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                @else
                    <li><a class="sh-nav__me" href="{{ route('visitor.index') }}">Your visits</a></li>
                    {{-- A second way out, reachable from every public page. A
                         signed-in session that can only be ended from one screen
                         is one people leave open. --}}
                    <li>
                        <form method="POST" action="{{ route('visitor.logout') }}">
                            @csrf
                            <button class="sh-nav__out" type="submit">Sign out</button>
                        </form>
                    </li>
                @endif
            @else
                <li><a href="{{ route('visitor.login') }}">Your visits</a></li>
            @endauth

            <li class="sh-nav__cta">
                <x-public.btn href="#" data-modal-open="sh-reserve" variant="onDark">Reserve your visit</x-public.btn>
            </li>
        </ul>
    </div>
</header>

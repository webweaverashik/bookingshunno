{{--
    The shell every error page sits in.

    STANDALONE, and that is the point. An error page is shown when something has
    already gone wrong, so it must not depend on anything that could BE the
    thing that went wrong: not layouts.app, not layouts.public, not the Metronic
    bundles, not a settings read, not the database. One stylesheet, one small
    script, and config — which is a PHP array and cannot throw.

    A 404 that itself falls over while looking up the studio's phone number is
    not a 404, it is a 500 with extra steps.

    THE LOOK is cyanotype: deep Prussian blue, cream type, paper grain. It is a
    process the studio actually runs, the accent colour on the public site comes
    from it, and it gives a dead end somewhere to go that still feels like
    Shunno rather than like a framework's default.

    Sections a page fills in:

        title     browser tab
        code      the status number, set enormous behind the text
        heading   one line, what happened
        text      one paragraph, in plain words
        path      optional; the address that failed
        seconds   countdown length, passed through @extends; 0 disables it
--}}

@php
    $contact = config('shunno.contact', []);

    /*
     | Admin or public, decided by the PATH rather than by auth().
     |
     | A logged-out admin hitting a bad /admin URL still wants the sign-in page,
     | not the landing page — and reading the session on an error page is one
     | more moving part that could fail. The path is already in front of us and
     | cannot throw.
     */
    $isAdmin = request()->is('admin') || request()->is('admin/*');

    $home = $isAdmin ? url('/admin') : url('/');
    $homeLabel = $isAdmin ? 'Back to the dashboard' : 'Back to the studio';

    $seconds = $seconds ?? 12;
@endphp

<!DOCTYPE html>
<html lang="en" class="no-js">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0F1E2B">
    <title>@yield('title', 'Something went wrong') — {{ config('app.name') }}</title>

    {{-- Fraunces, the public site's display face, with display=swap and Georgia
         behind it. The page paints immediately in the fallback and upgrades if
         the font arrives — so the one third-party request here can fail
         entirely without costing anything. Body copy stays on the system stack
         for the same reason. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/error.css') }}">

    {{-- Drops the no-js class before first paint, so the countdown line is
         never shown to somebody who will never see it count. Inline because it
         has to run before the body renders and is not worth a request. --}}
    <script>
        document.documentElement.className = '';
    </script>
</head>

<body>

    {{-- The number, set enormous and bleeding off two edges. Outlined rather
         than filled: solid at this size it would fight the text sitting on top
         of it, and hollow it reads as a watermark. aria-hidden because the
         heading already says what happened — a screen reader announcing "four
         zero four" before it is noise. --}}
    <div class="err-ghost" aria-hidden="true">@yield('code')</div>

    <div class="err-shell">

        <header class="err-top">
            {{-- onerror rather than a conditional: this page has to render on a
                 half-deployed server, which is exactly when it is most likely
                 to be needed. --}}
            <img src="{{ asset('img/shunno-white.png') }}" alt="{{ config('app.name') }}" class="err-mark"
                onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'err-wordmark',textContent:@json(config('app.name'))}))">
        </header>

        <main class="err-main">
            <p class="err-eyebrow"><span class="err-rule"></span>Error @yield('code')</p>

            <h1 class="err-heading">@yield('heading')</h1>

            <p class="err-text">@yield('text')</p>

            @hasSection('path')
                <p class="err-path">@yield('path')</p>
            @endif

            <div class="err-actions">
                <a class="err-btn err-btn--go" href="{{ $home }}" data-error-home>
                    {{ $homeLabel }}
                    <span class="err-arrow" aria-hidden="true">&rarr;</span>
                </a>

                {{-- history.back() rather than a referrer link: the referrer is
                     often absent, and going back keeps whatever the person had
                     typed into the page they came from. Revealed by the script,
                     since without it the button would do nothing. --}}
                <button type="button" class="err-btn err-btn--back" data-error-back hidden>Go back</button>
            </div>

            <p class="err-timer" data-error-timer data-seconds="{{ $seconds }}"></p>
        </main>

        <footer class="err-foot">
            @unless ($isAdmin)
                <span>
                    Expecting something else? Write to
                    <a href="mailto:{{ $contact['email'] ?? '' }}">{{ $contact['email'] ?? '' }}</a>
                    @if (!empty($contact['phone']))
                        or call <a href="tel:{{ preg_replace('/\s+/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a>
                    @endif
                </span>
            @endunless
            <span class="err-foot__mark">{{ config('app.name') }} &middot; Lalmatia, Dhaka</span>
        </footer>
    </div>

    <script src="{{ asset('js/error.js') }}" defer></script>
</body>

</html>

{{--
    PHASE 12C — the studio's mark at the top of every reservation email.

    Replaces Laravel's default, which renders the app name as text unless the
    slot happens to be the literal string "Laravel", in which case it shows
    Laravel's own logo. Neither is what we want.

    THREE THINGS TO KNOW ABOUT IMAGES IN EMAIL:

    1. The URL must be absolute and publicly reachable. asset() builds it from
       APP_URL, and these mails are QUEUED — a worker has no request to infer a
       host from, so APP_URL being wrong shows up here first, as a broken image
       in production while local looks fine.

    2. Many clients block images by default. The alt text is therefore the
       studio's name, not decoration: with images off, the header still reads
       "Shunno Art Cafe" rather than showing an empty box.

    3. Width and height are set as ATTRIBUTES as well as in CSS. Outlook on
       Windows ignores CSS dimensions on images and will render the file at its
       natural size, which for a large PNG means a logo the width of the screen.
--}}
<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            <img src="{{ asset('img/shunno-logo.png') }}" alt="{{ config('app.name') }}" class="logo"
                height="40" style="height: 40px; width: auto; max-width: 190px; border: 0;">
        </a>
    </td>
</tr>

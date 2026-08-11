<footer class="sh-footer">
    <div class="sh-wrap">
        <div class="sh-footer__top">
            <div>
                <div class="sh-footer__brand">
                    <img src="{{ asset('img/shunno-logo.png') }}" alt="" width="44" height="44">
                    <span>Shunno Art Cafe</span>
                </div>
                <p class="sh-footer__blurb">
                    A visual art studio fostering community engagement, cultural programmes,
                    workshops, exhibitions and an evening cafe. An artist-run cultural junction
                    since 2005.
                </p>
            </div>

            <div>
                <h4>Elsewhere at Shunno</h4>
                <ul>
                    <li><a href="https://studioshunno.net/about/">About the studio</a></li>
                    <li><a href="https://cafe.studioshunno.net/menu/">Cafe menu</a></li>
                    <li><a href="https://studioshunno.net/all_events/">Events &amp; exhibitions</a></li>
                    <li><a href="https://shop.studioshunno.net/">Shop</a></li>
                </ul>
            </div>

            <div>
                <h4>Reservations</h4>
                <ul>
                    <li><a href="{{ route('reservation.info') }}">Reserve your visit</a></li>
                    <li><a href="{{ route('home') }}#how">How it works</a></li>
                    {{-- PHASE 19: these need copies on this domain before SSLCommerz merchant approval --}}
                    <li><a href="https://studioshunno.net/privacy-policy-2/">Privacy policy</a></li>
                    <li><a href="https://studioshunno.net/refund-cancellation-policy/">Refund &amp; cancellation</a></li>
                </ul>
            </div>
        </div>

        {{--
            Payment strip. SSLCommerz requires the accepted-methods badge to be
            visible on the merchant site, and it is one of the things checked
            during merchant approval — so this is not decoration.
            See docs/image-manifest.md for how to obtain the exact asset.
        --}}
        <div class="sh-footer__pay">
            <p class="sh-footer__pay-label">Payments secured by SSLCommerz</p>
            <img class="sh-footer__pay-badge"
                 src="{{ asset('img/payments/sslcommerz.png') }}"
                 alt="Accepted payment methods: Visa, Mastercard, bKash, Nagad, Rocket, internet banking and other cards and wallets"
                 loading="lazy" decoding="async">
        </div>

        <div class="sh-footer__bottom">
            <p>&copy; {{ now()->year }} Shunno Art Cafe &middot; Trade Licence TRAD/DSCC/025165/2021</p>
            <p>
                <a href="https://www.facebook.com/shunnoartspace">Facebook</a> &middot;
                <a href="https://www.instagram.com/shunnoartspace/">Instagram</a> &middot;
                <a href="https://x.com/ShunnoArtSpace">X</a>
            </p>
        </div>
    </div>
</footer>

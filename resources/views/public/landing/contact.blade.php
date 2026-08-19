<section class="sh-band sh-band--sand" id="contact">
    <div class="sh-wrap sh-contact">
        <div>
            <p class="sh-eyebrow">Find us</p>
            <h2 class="sh-h2">Lalmatia, Dhaka</h2>

            <ul class="sh-facts">
                <li><span class="sh-facts__k">Address</span><span>5/6 Block F, Lalmatia<br>Dhaka 1207, Bangladesh</span></li>
                <li><span class="sh-facts__k">Sessions</span><span>Mon&ndash;Sat, 4:00&ndash;9:30 PM &middot; Closed Sunday</span></li>
                <li><span class="sh-facts__k">Cafe</span><span>Mon&ndash;Sat, 4:00&ndash;11:00 PM</span></li>
                <li><span class="sh-facts__k">Email</span><span><a href="mailto:{{ config('shunno.contact.email') }}">{{ config('shunno.contact.email') }}</a></span></li>
                <li><span class="sh-facts__k">Phone</span><span><a href="tel:{{ config('shunno.contact.phone') }}">+88 01799 020731</a></span></li>
            </ul>
        </div>

        <div class="sh-panel">
            <p class="sh-eyebrow">Getting here</p>
            <p>
                The studio is on Block F in Lalmatia. Open the location in Google Maps for
                turn-by-turn directions, or message us on WhatsApp and we'll help you find it.
            </p>
            {{-- An embedded Leaflet map can replace this; the plugin ships with Metronic --}}
            <x-public.btn :href="config('shunno.contact.maps')" variant="primary" :arrow="true">Get directions</x-public.btn>
        </div>
    </div>
</section>

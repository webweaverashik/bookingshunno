<section class="sh-band sh-cta">
    <img src="{{ asset('img/shunno/cta-bg.webp') }}" alt="" loading="lazy" decoding="async">

    <div class="sh-wrap">
        <p class="sh-eyebrow">Ready when you are</p>
        <h2 class="sh-h2">Reserve your visit</h2>
        <p class="sh-lede">Tell us what you'd like to do and when. We'll read it and come back to you.</p>

        <div class="sh-cta__actions">
            <x-public.btn href="#" data-modal-open="sh-reserve" variant="primary" :arrow="true">Reserve your visit</x-public.btn>
            <x-public.btn :href="'https://wa.me/' . config('shunno.contact.whatsapp')" variant="onDark">Ask on WhatsApp</x-public.btn>
        </div>
    </div>
</section>

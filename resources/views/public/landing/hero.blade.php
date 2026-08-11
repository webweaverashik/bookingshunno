<section class="sh-hero">
    <div class="sh-hero__bg">
        {{-- fetchpriority=high: this is the Largest Contentful Paint element --}}
        <img src="{{ asset('img/shunno/hero.jpg') }}"
             alt="A printmaking session in progress at Shunno"
             fetchpriority="high" decoding="async">
    </div>
    <div class="sh-hero__scrim"></div>

    <div class="sh-wrap sh-hero__inner">
        <div class="sh-hero__body">
            <p class="sh-hero__tagline">Create. Pause. Reflect.</p>

            <h1 class="sh-hero__title">Where every sip is a journey to <em>serenity</em></h1>

            <p class="sh-hero__text">
                An artist-run studio and evening cafe in Lalmatia &mdash; clay, print and paint
                sessions in 650m&sup2; of hand-crafted decor. Visits are arranged one at a time.
            </p>

            <div class="sh-hero__actions">
                <x-public.btn href="#" data-bs-toggle="modal" data-bs-target="#sh-reserve" variant="primary" :arrow="true">Reserve your visit</x-public.btn>
                <x-public.btn href="#experiences" variant="onDark">See the experiences</x-public.btn>
            </div>
        </div>

        <div class="sh-hero__strip">
            <span class="sh-hero__fact"><b>Open</b><span>Mon&ndash;Sat, 4:00&ndash;9:30 PM</span></span>
            <span class="sh-hero__fact"><b>Where</b><span>5/6 Block F, Lalmatia</span></span>
            <span class="sh-hero__fact"><b>Sessions from</b><span>150 BDT, materials included</span></span>
        </div>
    </div>
</section>

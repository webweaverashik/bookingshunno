(function () {
    'use strict';

    /**
     * Shunno Art Cafe — public site behaviour.
     *
     * Plain script, no modules, no bundler. Loaded with a normal <script defer>
     * from public/js/, the same way the Metronic assets are loaded in the admin.
     *
     * Deliberately small: sticky-nav state, mobile menu, hash-free in-page
     * scrolling, and a scroll reveal. No framework, no jQuery. Bootstrap's JS
     * bundle is not imported yet — nothing here needs it. Import it in the phase
     * that first uses a modal or a tooltip:  import * as bootstrap from 'bootstrap';
     */

    const prefersReducedMotion = () =>
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function initNav() {
        const nav = document.getElementById('sh-nav');
        const menu = document.getElementById('sh-menu');
        const toggle = document.querySelector('.sh-nav__toggle');

        if (!nav) return;

        // Transparent over the hero, solid once scrolled.
        const onScroll = () => nav.classList.toggle('is-stuck', window.scrollY > 40);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        // Pages without a hero start in the solid state.
        if (document.querySelector('.sh-band--afterNav')) {
            nav.classList.add('is-stuck');
            window.removeEventListener('scroll', onScroll);
        }

        if (!menu || !toggle) return;

        const closeMenu = () => {
            menu.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Open menu');
        };

        toggle.addEventListener('click', () => {
            const open = menu.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');

            // Keep the dropdown readable when opened at the top of the hero.
            if (open) nav.classList.add('is-stuck');
            else onScroll();
        });

        menu.addEventListener('click', (event) => {
            if (event.target.tagName !== 'A' || window.innerWidth > 940) return;
            closeMenu();
            onScroll();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && menu.classList.contains('is-open')) {
                closeMenu();
                toggle.focus();
            }
        });
    }

    /**
     * Scroll to in-page sections without writing "#experiences" into the address
     * bar. Anchors keep their real href so they still work with JS disabled, are
     * openable in a new tab, and remain crawlable — we only intercept the click.
     */
    function initQuietAnchors() {
        document.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0) return;
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

            const link = event.target.closest('a[href*="#"]');
            if (!link || link.hasAttribute('download') || link.target === '_blank') return;

            let url;
            try {
                url = new URL(link.href, window.location.href);
            } catch {
                return;
            }

            // Only handle anchors pointing at a section on this very page.
            if (url.origin !== window.location.origin) return;
            if (url.pathname !== window.location.pathname) return;

            const id = decodeURIComponent(url.hash.slice(1));
            if (!id) return;

            const target = document.getElementById(id);
            if (!target) return;

            event.preventDefault();
            target.scrollIntoView({
                behavior: prefersReducedMotion() ? 'auto' : 'smooth',
                block: 'start',
            });

            // Move keyboard focus with the viewport, without a second jump.
            if (!target.hasAttribute('tabindex')) {
                target.setAttribute('tabindex', '-1');
                target.addEventListener('blur', () => target.removeAttribute('tabindex'), { once: true });
            }
            target.focus({ preventScroll: true });
        });
    }

    /**
     * Scroll-to-top control. The ring around the button doubles as a reading
     * progress indicator, so the element earns its place rather than just sitting
     * in the corner.
     */
    function initScrollTop() {
        const button = document.getElementById('sh-totop');
        if (!button) return;

        const ring = button.querySelector('.sh-totop__ring .fg');
        const circumference = ring ? 2 * Math.PI * Number(ring.getAttribute('r')) : 0;

        if (ring) {
            ring.style.strokeDasharray = `${circumference}`;
            ring.style.strokeDashoffset = `${circumference}`;
        }

        let ticking = false;

        const update = () => {
            ticking = false;

            const scrolled = window.scrollY;
            const max = document.documentElement.scrollHeight - window.innerHeight;

            button.classList.toggle('is-visible', scrolled > window.innerHeight * 0.6);

            if (ring && max > 0) {
                const progress = Math.min(scrolled / max, 1);
                ring.style.strokeDashoffset = `${circumference * (1 - progress)}`;
            }
        };

        const onScroll = () => {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(update);
        };

        update();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll, { passive: true });

        button.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: prefersReducedMotion() ? 'auto' : 'smooth',
            });
            // Send keyboard focus back to the start of the document too.
            const skip = document.querySelector('.sh-skip');
            if (skip) skip.focus({ preventScroll: true });
        });
    }

    function initReveal() {
        const targets = document.querySelectorAll('.sh-band');

        // Content must never depend on JS to become visible.
        if (prefersReducedMotion() || !('IntersectionObserver' in window) || !targets.length) return;

        targets.forEach((el) => el.classList.add('sh-reveal'));

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('is-in');
                    observer.unobserve(entry.target);
                });
            },
            { rootMargin: '0px 0px -8% 0px', threshold: 0.05 }
        );

        targets.forEach((el) => observer.observe(el));
    }

    document.addEventListener('DOMContentLoaded', () => {
        initNav();
        initQuietAnchors();
        initScrollTop();
        initReveal();
    });
})();

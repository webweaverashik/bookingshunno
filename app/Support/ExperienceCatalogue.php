<?php

namespace App\Support;

/**
 * The workshop menu, in code.
 *
 * Titles, prices, durations and descriptions come from the printed Shunno
 * workshop menu. Rates are per person and include materials. Image paths point
 * at public/img/shunno/ — see docs/image-manifest.md, several pairings still
 * need a visual check.
 *
 * PHASE 6: this class disappears. Replace its callers with
 * Workshop::active()->ordered()->get(). Both the landing page and the
 * reservation information page read everything through data_get(), so the swap
 * only requires the Workshop model to expose the same attribute names.
 */
class ExperienceCatalogue
{
    /**
     * Ordered by session length, shortest first.
     */
    public static function all(): array
    {
        return [
            [
                'slug'        => 'visit',
                'category'    => 'Other purposes',
                'title'       => 'A visit to the space',
                'medium'      => 'Exhibitions, reading or quiet work',
                'description' => 'Time in the space without a session. Entry includes a 50 BDT coupon, redeemable against food and drinks.',
                'price'       => 150,
                'hours'       => 1,
                'image'       => 'img/shunno/exp-visit.jpg',
            ],
            [
                'slug'        => 'paint',
                'category'    => 'Express',
                'title'       => 'Paint session',
                'medium'      => 'Acrylic or watercolour',
                'description' => 'A short, refreshing creative break. Learn a technique, make a piece, take it home. Good for first-timers.',
                'price'       => 800,
                'hours'       => 2,
                'image'       => 'img/shunno/exp-paint.webp',
            ],
            [
                'slug'        => 'clay',
                'category'    => 'Express',
                'title'       => 'Clay session',
                'medium'      => 'Hand-building or the wheel',
                'description' => 'Work a lump of clay into something of your own. Learn a technique, make a piece, take it home.',
                'price'       => 900,
                'hours'       => 2,
                'image'       => 'img/shunno/exp-clay.jpg',
            ],
            [
                'slug'        => 'printmaking',
                'category'    => 'Express',
                'title'       => 'Printmaking',
                'medium'      => 'Linocut or monotype',
                'description' => "Cut, ink and pull your own prints on the studio's presses. A short session with something finished at the end of it.",
                'price'       => 900,
                'hours'       => 2,
                'image'       => 'img/shunno/exp-print.jpg',
            ],
            [
                'slug'        => 'climate',
                'category'    => 'Chalantika special',
                'title'       => 'Climate art workshop',
                'medium'      => 'Art connected to environment and society',
                'description' => "Create while thinking about climate, memory and collective experience. Part of Shunno's travelling Chalantika programme.",
                'price'       => 1000,
                'hours'       => 2,
                'image'       => 'img/shunno/exp-climate.jpg',
            ],
            [
                'slug'        => 'therapeutic',
                'category'    => 'Mindful',
                'title'       => 'Therapeutic art session',
                'medium'      => 'Guided, unhurried making',
                'description' => 'A calm session focused on relaxation and reflection. No outcome required, the point is the time itself.',
                'price'       => 1500,
                'hours'       => 3,
                'image'       => 'img/shunno/exp-mindful.jpg',
            ],
            [
                'slug'        => 'mixed-media',
                'category'    => 'Immersive',
                'title'       => 'Mixed media exploration',
                'medium'      => 'Paint, print and clay combined',
                'description' => 'A deeper, slower experience: explore multiple media, experiment without pressure, develop a final work with guidance.',
                'price'       => 2000,
                'hours'       => 4,
                'image'       => 'img/shunno/exp-mixed.png',
            ],
        ];
    }
}

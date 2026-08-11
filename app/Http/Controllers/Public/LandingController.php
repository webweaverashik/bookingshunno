<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        return view('public.landing', [
            'experiences' => $this->experiences(),
        ]);
    }

    /**
     * Experience catalogue, ordered by session length.
     *
     * Titles, prices, durations and descriptions come from the printed Shunno
     * workshop menu. Rates are per person and include materials.
     *
     * Image paths point at public/img/shunno/. See docs/image-manifest.md —
     * several pairings are best guesses and need a visual check.
     *
     * PHASE 6: replace this method with Workshop::active()->ordered()->get().
     * The view reads everything through data_get(), so the swap is a one-line
     * change as long as the Workshop model exposes the same attribute names.
     */
    private function experiences(): array
    {
        return [
            [
                'category'    => 'Other purposes',
                'title'       => 'A visit to the space',
                'medium'      => 'Exhibitions, reading or quiet work',
                'description' => 'Time in the space without a session. Entry includes a 50 BDT coupon, redeemable against food and drinks.',
                'price'       => 150,
                'hours'       => 1,
                'image'       => 'img/shunno/exp-visit.jpg',
            ],
            [
                'category'    => 'Express',
                'title'       => 'Paint session',
                'medium'      => 'Acrylic or watercolour',
                'description' => 'A short, refreshing creative break. Learn a technique, make a piece, take it home. Good for first-timers.',
                'price'       => 800,
                'hours'       => 2,
                'image'       => 'img/shunno/exp-paint.webp',
            ],
            [
                'category'    => 'Express',
                'title'       => 'Clay session',
                'medium'      => 'Hand-building or the wheel',
                'description' => 'Work a lump of clay into something of your own. Learn a technique, make a piece, take it home.',
                'price'       => 900,
                'hours'       => 2,
                'image'       => 'img/shunno/exp-clay.jpg',
            ],
            [
                'category'    => 'Express',
                'title'       => 'Printmaking',
                'medium'      => 'Linocut or monotype',
                'description' => "Cut, ink and pull your own prints on the studio's presses. A short session with something finished at the end of it.",
                'price'       => 900,
                'hours'       => 2,
                'image'       => 'img/shunno/exp-print.jpg',
            ],
            [
                'category'    => 'Chalantika special',
                'title'       => 'Climate art workshop',
                'medium'      => 'Art connected to environment and society',
                'description' => "Create while thinking about climate, memory and collective experience. Part of Shunno's travelling Chalantika programme.",
                'price'       => 1000,
                'hours'       => 2,
                'image'       => 'img/shunno/exp-climate.jpg',
            ],
            [
                'category'    => 'Mindful',
                'title'       => 'Therapeutic art session',
                'medium'      => 'Guided, unhurried making',
                'description' => 'A calm session focused on relaxation and reflection. No outcome required, the point is the time itself.',
                'price'       => 1500,
                'hours'       => 3,
                'image'       => 'img/shunno/exp-mindful.jpg',
            ],
            [
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

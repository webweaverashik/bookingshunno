<?php

namespace Database\Seeders;

use App\Enums\Workshop\WorkshopCategory;
use App\Models\Workshop\Workshop;
use Illuminate\Database\Seeder;

/**
 * The printed Shunno workshop menu, as seed data.
 *
 * PHASE 6: App\Support\ExperienceCatalogue is deleted, so the menu lives here
 * and nowhere else. From this point the admin panel is the source of truth —
 * this seeder only bootstraps an empty database.
 *
 * updateOrCreate on the slug means re-running it restores the printed values
 * without duplicating rows. It will therefore overwrite admin edits to these
 * seven workshops: run it on a fresh database, not on a live one.
 *
 * Prices, durations and descriptions come from the printed menu. Rates are per
 * person and include materials.
 */
class WorkshopSeeder extends Seeder
{
    /**
     * AWAITING CONFIRMATION: the printed menu gives no per-session capacity.
     * 12 is a placeholder so the column is not null. Phase 7 cannot enforce
     * availability until the real numbers are entered.
     */
    private const PLACEHOLDER_CAPACITY = 12;

    public function run(): void
    {
        foreach ($this->menu() as $index => $entry) {
            Workshop::updateOrCreate(
                ['slug' => $entry['slug']],
                [
                    'title' => $entry['title'],
                    'medium' => $entry['medium'],
                    'category' => $entry['category'],
                    'short_description' => $entry['description'],
                    'description' => $entry['description'],
                    'image_path' => $entry['image'],
                    'price' => $entry['price'],
                    'price_basis' => 'per_person',
                    'duration_minutes' => $entry['minutes'],
                    'min_participants' => 1,
                    'max_participants' => self::PLACEHOLDER_CAPACITY,
                    'materials_included' => true,
                    'requires_experience' => false,
                    'is_active' => true,
                    'is_featured' => false,
                    'sort_order' => $index,
                ],
            );
        }

        Workshop::forgetMenu();
    }

    /**
     * Ordered by session length, shortest first.
     */
    private function menu(): array
    {
        return [
            [
                'slug' => 'visit',
                'category' => WorkshopCategory::Other,
                'title' => 'A visit to the space',
                'medium' => 'Exhibitions, reading or quiet work',
                'description' => 'Time in the space without a session. Entry includes a 50 BDT coupon, redeemable against food and drinks.',
                'price' => 150,
                'minutes' => 60,
                'image' => 'img/shunno/exp-visit.jpg',
            ],
            [
                'slug' => 'paint',
                'category' => WorkshopCategory::Express,
                'title' => 'Paint session',
                'medium' => 'Acrylic or watercolour',
                'description' => 'A short, refreshing creative break. Learn a technique, make a piece, take it home. Good for first-timers.',
                'price' => 800,
                'minutes' => 120,
                'image' => 'img/shunno/exp-paint.webp',
            ],
            [
                'slug' => 'clay',
                'category' => WorkshopCategory::Express,
                'title' => 'Clay session',
                'medium' => 'Hand-building or the wheel',
                'description' => 'Work a lump of clay into something of your own. Learn a technique, make a piece, take it home.',
                'price' => 900,
                'minutes' => 120,
                'image' => 'img/shunno/exp-clay.jpg',
            ],
            [
                'slug' => 'printmaking',
                'category' => WorkshopCategory::Express,
                'title' => 'Printmaking',
                'medium' => 'Linocut or monotype',
                'description' => "Cut, ink and pull your own prints on the studio's presses. A short session with something finished at the end of it.",
                'price' => 900,
                'minutes' => 120,
                'image' => 'img/shunno/exp-print.jpg',
            ],
            [
                'slug' => 'climate',
                'category' => WorkshopCategory::Chalantika,
                'title' => 'Climate art workshop',
                'medium' => 'Art connected to environment and society',
                'description' => "Create while thinking about climate, memory and collective experience. Part of Shunno's travelling Chalantika programme.",
                'price' => 1000,
                'minutes' => 120,
                'image' => 'img/shunno/exp-climate.jpg',
            ],
            [
                'slug' => 'therapeutic',
                'category' => WorkshopCategory::Mindful,
                'title' => 'Therapeutic art session',
                'medium' => 'Guided, unhurried making',
                'description' => 'A calm session focused on relaxation and reflection. No outcome required, the point is the time itself.',
                'price' => 1500,
                'minutes' => 180,
                'image' => 'img/shunno/exp-mindful.jpg',
            ],
            [
                'slug' => 'mixed-media',
                'category' => WorkshopCategory::Immersive,
                'title' => 'Mixed media exploration',
                'medium' => 'Paint, print and clay combined',
                'description' => 'A deeper, slower experience: explore multiple media, experiment without pressure, develop a final work with guidance.',
                'price' => 2000,
                'minutes' => 240,
                'image' => 'img/shunno/exp-mixed.png',
            ],
        ];
    }
}

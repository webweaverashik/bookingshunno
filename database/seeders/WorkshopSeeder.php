<?php

namespace Database\Seeders;

use App\Enums\WorkshopCategory;
use App\Models\Workshop;
use App\Support\ExperienceCatalogue;
use Illuminate\Database\Seeder;

/**
 * Seeds the workshops table from the catalogue class the public pages already
 * use, so there is exactly one copy of the printed menu in the codebase until
 * Phase 6 removes the class entirely.
 *
 * Prices, durations and descriptions are from the printed workshop menu.
 * Rates are per person and include materials.
 */
class WorkshopSeeder extends Seeder
{
    private const CATEGORY_MAP = [
        'Express'            => WorkshopCategory::Express,
        'Immersive'          => WorkshopCategory::Immersive,
        'Mindful'            => WorkshopCategory::Mindful,
        'Chalantika special' => WorkshopCategory::Chalantika,
        'Other purposes'     => WorkshopCategory::Other,
    ];

    public function run(): void
    {
        foreach (ExperienceCatalogue::all() as $index => $experience) {
            Workshop::updateOrCreate(
                ['slug' => $experience['slug']],
                [
                    'title'             => $experience['title'],
                    'medium'            => $experience['medium'],
                    'category'          => self::CATEGORY_MAP[$experience['category']] ?? WorkshopCategory::Other,
                    'short_description' => $experience['description'],
                    'description'       => $experience['description'],
                    'image_path'        => $experience['image'],
                    'price'             => $experience['price'],
                    'price_basis'       => 'per_person',
                    'duration_minutes'  => (int) $experience['hours'] * 60,
                    'min_participants'  => 1,
                    // AWAITING CONFIRMATION: the printed menu gives no capacity
                    // per session. 12 is a placeholder so the column is not
                    // null — Phase 7 needs the real numbers before availability
                    // can mean anything.
                    'max_participants'  => 12,
                    'materials_included' => true,
                    'requires_experience' => false,
                    'is_active'         => true,
                    'sort_order'        => $index,
                ],
            );
        }
    }
}

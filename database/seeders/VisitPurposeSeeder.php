<?php

namespace Database\Seeders;

use App\Models\Reservation\VisitPurpose;
use Illuminate\Database\Seeder;

/**
 * Seeded from the live Google Form.
 *
 * PLEASE CONFIRM these eight match the current form before this runs in
 * production. Note also the "Memebrs" typo on the live form — worth fixing
 * there while the wording is being checked.
 */
class VisitPurposeSeeder extends Seeder
{
    public function run(): void
    {
        $purposes = [
            ['slug' => 'workshop',   'name' => 'Workshop or learning session',    'is_chargeable' => true,  'is_invitation_only' => false],
            ['slug' => 'exhibition', 'name' => 'Exhibition or event',             'is_chargeable' => false, 'is_invitation_only' => false],
            ['slug' => 'meeting',    'name' => 'Creative meeting or collaboration', 'is_chargeable' => true, 'is_invitation_only' => false],
            ['slug' => 'quiet-work', 'name' => 'Research, reading or quiet work',  'is_chargeable' => true,  'is_invitation_only' => false],
            ['slug' => 'gathering',  'name' => 'Community or cultural gathering',  'is_chargeable' => true,  'is_invitation_only' => false],
            ['slug' => 'cafe',       'name' => 'A short cafe visit',               'is_chargeable' => true,  'is_invitation_only' => false],
            ['slug' => 'invited',    'name' => 'Invited guest',                    'is_chargeable' => false, 'is_invitation_only' => true],
            ['slug' => 'other',      'name' => 'Something else',                   'is_chargeable' => true,  'is_invitation_only' => false],
        ];

        foreach ($purposes as $index => $purpose) {
            VisitPurpose::updateOrCreate(
                ['slug' => $purpose['slug']],
                $purpose + ['sort_order' => $index, 'is_active' => true],
            );
        }
    }
}

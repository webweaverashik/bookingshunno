<?php

namespace Database\Seeders;

use App\Models\OperatingHour;
use Illuminate\Database\Seeder;

/**
 * From the printed reservation card: sessions six days a week, 4:00 PM to
 * 9:30 PM, except Sundays. The cafe itself stays open later, but the studio
 * window is what governs bookable time.
 */
class OperatingHourSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(0, 6) as $day) {
            $closed = $day === 0;   // Sunday

            OperatingHour::updateOrCreate(
                ['day_of_week' => $day],
                [
                    'opens_at'  => $closed ? null : '16:00:00',
                    'closes_at' => $closed ? null : '21:30:00',
                    'is_closed' => $closed,
                ],
            );
        }
    }
}

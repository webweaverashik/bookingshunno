<?php

namespace App\Support;

/**
 * Reasons people come to Shunno.
 *
 * Seeded from the live Google Form. Two things the existing responses proved,
 * and which the schema has to respect:
 *
 *  1. Visitors pick MORE THAN ONE. Several of the 13 responses carry two to
 *     four purposes, so this is a multi-select, not a single choice.
 *  2. The list has already changed once — early responses contain
 *     "Exhibition or Art Viewing" and "Art Collection Viewing", which the
 *     current form no longer offers. That is why this lives in a class today
 *     and a `visit_purposes` table in Phase 4, never a PHP enum.
 *
 * PLEASE CONFIRM this list matches the current form before Phase 4 seeds it.
 */
class VisitPurposes
{
    public static function all(): array
    {
        return [
            'workshop'    => 'Workshop or learning session',
            'exhibition'  => 'Exhibition or event',
            'meeting'     => 'Creative meeting or collaboration',
            'quiet-work'  => 'Research, reading or quiet work',
            'gathering'   => 'Community or cultural gathering',
            'cafe'        => 'A short cafe visit',
            'invited'     => 'Invited guest',
            'other'       => 'Something else',
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }
}

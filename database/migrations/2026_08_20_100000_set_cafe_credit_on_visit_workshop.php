<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give "A visit to the space" the café credit the proposal promises.
 *
 * The column was added with a default of zero and deliberately not seeded, on
 * the reasoning that seeding it would overwrite whatever the studio had chosen.
 * Sound in principle — except the form never carried the field, so there was
 * nothing for the studio to choose WITH, the figure stayed at zero on every
 * workshop, and no café credit was ever issued for a paid visit.
 *
 * The form field is added in the same phase as this migration. This exists so
 * the feature works on the existing data without somebody having to know to go
 * and set it.
 *
 * GUARDED THREE WAYS, because a data migration that overwrites a deliberate
 * choice is worse than one that does nothing:
 *
 *   only the visit workshop, matched on slug
 *   only where the figure is still zero, so a studio that has already set 75
 *     or decided on nothing keeps its answer
 *   50 BDT, which is the figure in the proposal and in the seeded description
 *     of that experience
 *
 * Irreversible on purpose. down() would have to guess whether the 50 it is
 * looking at came from here or from the studio, and clearing a live figure to
 * roll back a migration is the wrong risk to take.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('workshops')
            ->where('slug', 'visit')
            ->where('cafe_credit_per_person', 0)
            ->update(['cafe_credit_per_person' => 50]);
    }

    public function down(): void
    {
        // Intentionally empty — see the note above.
    }
};

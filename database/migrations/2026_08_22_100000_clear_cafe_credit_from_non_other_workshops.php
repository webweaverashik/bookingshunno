<?php

use App\Enums\Workshop\WorkshopCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clear café credit from every category that is not allowed to carry it.
 *
 * The client's rule — credit is earned by "Other purposes" bookings and by
 * nothing else — is enforced from now on by WorkshopRequest and by the form,
 * which no longer shows the field. Neither of those touches a row that already
 * has a figure on it.
 *
 * In practice this should find nothing. The column was unreachable from the
 * form until Phase 35A, and the only row ever seeded with a figure is "A visit
 * to the space", which is category Other and therefore allowed to keep it. It
 * exists for the window between 35A and now, during which the field was live on
 * every category and somebody could reasonably have filled it in on a clay
 * session while testing.
 *
 * WHY NOT A CHECK CONSTRAINT. MySQL would enforce it, and the studio would then
 * hit a raw SQL error the first time they changed a category on a workshop that
 * still had a figure — with no way to fix it from the panel. Zeroing on save is
 * the same rule with a recoverable failure mode.
 *
 * Reversible in the only sense that matters: down() does nothing, because
 * putting the figures back would mean knowing what they were, and a migration
 * that invents money is worse than one that cannot be undone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('workshops')
            ->whereNotIn('category', WorkshopCategory::creditBearing())
            ->where('cafe_credit_per_person', '>', 0)
            ->update(['cafe_credit_per_person' => 0]);
    }

    public function down(): void
    {
        // Intentionally empty — see the note above.
    }
};

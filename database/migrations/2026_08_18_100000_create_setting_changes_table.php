<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who changed a setting.
 *
 * ONE TABLE, ONE PURPOSE. The first draft of this phase reached for
 * spatie/laravel-activitylog and applied it to eight models, which was more
 * machinery than the problem deserved: six of those eight are already covered
 * by purpose-built records, or are low-stakes reference data in a studio where
 * everyone knows who did what.
 *
 * This is the part that was not covered, and it exists because of a decision
 * made in Phase 19.
 *
 * When the SSLCommerz credentials moved out of .env and into the settings
 * table, the old guarantee went with them — that only somebody who could deploy
 * could change where the studio's money goes. What replaced it was encryption
 * at rest, a rate limit and a warning banner. None of those records WHO.
 *
 * The three settings that break quietly are all here:
 *
 *   sslcommerz.mode          sandbox looks successful to everybody and never
 *                            settles; found at month end against a statement
 *   booking_fee_percentage   changes what visitors are charged from then on
 *   mail.host                wrong, and every notification stops with no error
 *
 * All three are now editable from a session. This is the row that says by whom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_changes', function (Blueprint $table) {
            $table->id();

            $table->string('key', 191);

            /*
             | The before and after, as text.
             |
             | NULLABLE, and null means two different things depending on
             | is_secret: either the setting genuinely had no previous value, or
             | it is a credential whose value is never written here at all. The
             | flag is what tells them apart, and it is why the flag exists
             | rather than a sentinel string that could collide with a real
             | value.
             */
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();

            /*
             | Set for the SMTP password and both SSLCommerz store passwords.
             |
             | When true, neither value column is populated. Not masked, not
             | encrypted — ABSENT. There is no reading of a credential that this
             | log needs to do its job: "the live store password was changed by
             | Rahman on Tuesday" is the whole useful statement, and storing the
             | value would put a second copy of it in a table built for reading
             | on screen and exporting to CSV.
             */
            $table->boolean('is_secret')->default(false);

            /*
             | nullOnDelete rather than cascade. A staff account being removed
             | must not take its record of changes with it — that is precisely
             | the moment somebody wants to look. The row survives with a null
             | causer and the viewer shows the account as removed.
             */
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();

            // Kept alongside the user, not instead of it. A change made from an
            // unfamiliar address by a known account is the interesting case.
            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->nullable();

            /*
             | Two indexes, both for the viewer. Every query on that screen is
             | either "this date range, newest first" or "everything that ever
             | happened to this key" — the second is how you answer "when did
             | the booking fee last move".
             */
            $table->index('created_at');
            $table->index(['key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_changes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 10A — Admin may set a reservation's total to any figure.
 *
 * Stored separately from total_amount rather than by simply writing over it.
 * total_amount is what the studio will charge; subtotal, discount_amount and
 * the line items are what the price list says it should be. Overwriting
 * total_amount would collapse those into one number and lose the ability to
 * answer "what was agreed, and how far is it from the standard price" — which
 * is exactly the question a report or a dispute asks.
 *
 * It also survives re-pricing. When a Manager later changes the party size,
 * PricingService recalculates the subtotal and the discount, and the agreed
 * figure has to still be there afterwards rather than being quietly replaced by
 * a computed one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // NULL means "no manual price, use the calculated total".
            $table->decimal('total_override', 12, 2)->nullable()->after('discount_reason');

            // Required whenever the override is set — a figure with no
            // explanation is unusable to whoever reads the record next.
            $table->string('total_override_reason')->nullable()->after('total_override');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['total_override', 'total_override_reason']);
        });
    }
};

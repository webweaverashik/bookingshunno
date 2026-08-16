<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 14A — which experiences earn café credit, and how much.
 *
 * The client's rule is that only the non-session visit types earn it. That
 * could have been hard-coded against WorkshopCategory::Other, and this is
 * deliberately not: a per-workshop figure means the studio can add a second
 * credit-earning visit type, or change the amount for a season, as an admin
 * edit rather than a deploy. It also gives them the editable amount they asked
 * for without a second settings key.
 *
 * Zero means this experience earns nothing, which is the default and stays the
 * default for every workshop. Only "A visit to the space" gets a figure, and it
 * is set by hand rather than seeded — seeding 50 here would overwrite whatever
 * the studio had already chosen the next time the seeder ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table) {
            $table->decimal('cafe_credit_per_person', 10, 2)
                ->default(0)
                ->after('price_basis');
        });
    }

    public function down(): void
    {
        Schema::table('workshops', function (Blueprint $table) {
            $table->dropColumn('cafe_credit_per_person');
        });
    }
};

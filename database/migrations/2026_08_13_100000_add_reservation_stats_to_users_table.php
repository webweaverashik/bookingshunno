<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 4 CLOSEOUT.
 *
 * ReservationService::createFromPublicRequest() has always ended with
 *
 *     $user->increment('total_reservations');
 *     $user->forceFill(['last_reservation_at' => now()])->save();
 *
 * against columns that were never created, which is why the service could not
 * be wired up. Phase 8 reads both for the visitor list, and the returning-
 * visitor flow in Phase 15 reads last_reservation_at to decide what to prefill.
 *
 * Kept denormalised rather than counted on demand: the visitor table shows
 * these for every row, and a count subquery per row is a poor trade for two
 * columns the service already maintains inside its transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('total_reservations')->default(0)->after('source');
            $table->timestamp('last_reservation_at')->nullable()->after('total_reservations');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['total_reservations', 'last_reservation_at']);
        });
    }
};

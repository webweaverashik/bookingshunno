<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line items, with the price snapshotted at request time.
 *
 * Two reasons this is not just a workshop_id on the reservation:
 *  - Raising the clay session from 900 to 1000 must not silently rewrite what
 *    last month's visitors were quoted.
 *  - The Google Form data proves visitors combine purposes, so a reservation
 *    covering more than one thing has to be representable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workshop_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title_snapshot');
            $table->decimal('unit_price', 12, 2);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('line_total', 12, 2);
            $table->unsignedSmallInteger('duration_minutes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_items');
    }
};

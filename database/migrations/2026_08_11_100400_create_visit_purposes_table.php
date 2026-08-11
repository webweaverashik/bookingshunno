<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A table rather than an enum, deliberately.
 *
 * The live Google Form's purpose list has already changed once: early responses
 * contain "Exhibition or Art Viewing" and "Art Collection Viewing", which the
 * current form no longer offers. An enum would have needed a migration and
 * would have orphaned that historical data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_purposes', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            $table->boolean('is_chargeable')->default(true);
            $table->boolean('is_invitation_only')->default(false);
            $table->foreignId('default_workshop_id')->nullable()->constrained('workshops')->nullOnDelete();

            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_purposes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshops', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('medium')->nullable();            // "Acrylic or watercolour"
            $table->string('category', 32)->index();         // WorkshopCategory
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();

            $table->string('image_path')->nullable();
            $table->json('gallery')->nullable();

            // DECIMAL, never FLOAT. Half of an odd total must not drift.
            $table->decimal('price', 12, 2);
            $table->string('price_basis', 20)->default('per_person');

            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('min_participants')->default(1);
            $table->unsignedSmallInteger('max_participants')->default(12);

            $table->boolean('materials_included')->default(true);
            $table->boolean('requires_experience')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshops');
    }
};

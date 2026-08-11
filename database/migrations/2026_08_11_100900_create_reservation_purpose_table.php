<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_purpose', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_purpose_id')->constrained()->cascadeOnDelete();

            $table->unique(['reservation_id', 'visit_purpose_id'], 'reservation_purpose_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_purpose');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sign-in audit. Failed attempts are recorded against an email string rather
 * than a user id, so an attempt on an address that does not exist is still
 * visible without creating anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_activities');
    }
};

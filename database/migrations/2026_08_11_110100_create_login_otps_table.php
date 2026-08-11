<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_otps', function (Blueprint $table) {
            $table->id();

            // Unique: OtpService uses updateOrCreate, which would race into
            // duplicate rows on a double submit without this.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // The code is hashed, never stored in the clear. VARCHAR, because a
            // numeric column would eat the leading zero on a code like 048213.
            $table->string('code');

            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);

            // Survives a resend; `attempts` does not.
            $table->unsignedSmallInteger('total_attempts')->default(0);
            $table->unsignedTinyInteger('resend_count')->default(0);

            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_otps');
    }
};

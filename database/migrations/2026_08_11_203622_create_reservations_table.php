<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 20)->unique(); // SHN-2608-A7K3
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('reserved_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('participants');

            $table->text('special_requests')->nullable();
            $table->string('status', 32)->default('pending');

            // Money is DECIMAL throughout. Never FLOAT, never a single
            // catch-all "amount" column — a 50% booking fee has to be
            // distinguishable from a paid-in-full reservation.
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('discount_reason')->nullable();

            $table->text('admin_notes')->nullable();
            $table->text('decline_reason')->nullable();
            $table->text('info_request_message')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->string('source', 32)->default('web');
            $table->ipAddress('submitted_ip')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // The admin queue is "open reservations, soonest first"; the
            // capacity check is "this date, these statuses".
            $table->index(['reserved_date', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};

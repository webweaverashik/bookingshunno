<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 12A — one row per payment REQUEST.
 *
 * Not one row per transaction. A request is the studio saying "this much, by
 * then"; a transaction is an attempt to satisfy it. SSLCommerz will produce
 * several attempts against a single request — a failed card, a retry, a
 * successful bKash — and those belong in a payment_transactions table that
 * Phase 13 adds. Modelling them as one thing now would mean a failed attempt
 * either overwriting the request or creating a second one.
 *
 * The gateway_* columns below are the exception, and they are deliberate: they
 * hold the attempt that SUCCEEDED, denormalised onto the request, so the admin
 * panel can answer "how was this paid" without a join. Phase 13 fills them and
 * keeps the full attempt history separately.
 *
 * No soft deletes. A payment record is the answer to "what did we ask for and
 * what did we get", and a deleted_at column on that invites someone to make an
 * awkward figure disappear. Cancelling is a status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            /*
             | Two identifiers, because they do different jobs.
             |
             | reference is read out over the phone and printed on a receipt, so
             | it is short and uses the same unambiguous alphabet as the
             | reservation code. That also makes it guessable, which is fine for
             | something you quote to staff who already know who you are.
             |
             | token is what appears in the payment URL. A visitor reaches their
             | payment page without signing in, so the URL IS the credential and
             | it has to be long enough that guessing is hopeless. Never show
             | this one to anybody.
             */
            $table->string('reference', 20)->unique();      // PAY-2608-K4RT
            $table->string('token', 64)->unique();

            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();

            $table->string('type', 20);                     // booking_fee | full
            $table->unsignedTinyInteger('percentage');      // 50 or 100

            /*
             | Snapshots, all three. The reservation's total can move after a
             | request is sent — an Admin agrees a different figure, a party
             | size is corrected — and when it does, what the visitor was asked
             | for must not silently change underneath them. reservation_total
             | records what the reservation was worth at the moment of asking,
             | so a later divergence is visible rather than invisible.
             |
             | DECIMAL, never FLOAT. PricingService works in integer poisha and
             | rounds once at the boundary; this column stores that boundary
             | value.
             */
            $table->decimal('reservation_total', 12, 2);
            $table->decimal('amount_due', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);

            $table->string('status', 20)->default('pending');

            $table->timestamp('due_at');
            $table->timestamp('paid_at')->nullable();

            // The successful attempt, denormalised. Null until something is paid.
            $table->string('method', 40)->nullable();
            $table->string('gateway_reference', 100)->nullable();
            $table->string('gateway_status', 40)->nullable();
            $table->json('gateway_payload')->nullable();

            $table->text('note')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // "What is outstanding and overdue" is the payments register's
            // default view, and the operational question the client will ask
            // most often.
            $table->index(['status', 'due_at']);
            $table->index(['reservation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

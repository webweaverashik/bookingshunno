<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A record of every email the system sends.
 *
 * §18 asks for communication history and no earlier phase built it. Until now
 * the only trace of an email was a line in laravel.log, and only while
 * MAIL_MAILER=log — in production over SMTP there is no record at all. When a
 * visitor says "I never received it", nobody can answer, and the studio cannot
 * even resend without withdrawing the payment request and issuing a new one,
 * which puts a cancellation into the history for what was a mail delivery
 * problem.
 *
 * WHY THE CONTEXT COLUMNS. reservation_id, payment_id, transaction_id and note
 * are what a resend needs to rebuild the same message. Storing the rendered
 * HTML instead would freeze wording that the client is still revising, and
 * storing nothing would mean a resend could only ever be a fresh send with
 * today's data — which for a payment request would quietly change the amount if
 * the reservation had been edited.
 *
 * The body is deliberately NOT stored. It is reconstructable, it would be the
 * largest column in the database by an order of magnitude, and an archive of
 * every visitor's email is a liability rather than an asset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table) {
            $table->id();

            $table->string('to_email');
            $table->string('subject');

            // ReservationMailKind's value. A plain string rather than an enum
            // column so an email added in a later phase does not need a
            // migration before it can be logged.
            $table->string('kind', 40)->nullable();

            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()
                ->constrained('payment_transactions')->nullOnDelete();

            // The decision note, the question asked, the reason declined —
            // whatever was written into that specific message.
            $table->text('note')->nullable();

            $table->string('status', 20)->default('queued');
            $table->text('error')->nullable();

            $table->timestamp('queued_at');
            $table->timestamp('sent_at')->nullable();

            // Set by the mail transport once it has actually handed the message
            // over. The only hard evidence that it left the building.
            $table->string('message_id', 255)->nullable();

            // Null for the automatic send; the staff member's id for a resend.
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_resend')->default(false);

            // Which original this repeats. Lets the drawer group a resend under
            // the message it repeats rather than showing two unrelated rows.
            $table->foreignId('resend_of')->nullable()->constrained('communications')->nullOnDelete();

            $table->timestamps();

            $table->index(['reservation_id', 'created_at']);
            $table->index(['payment_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};

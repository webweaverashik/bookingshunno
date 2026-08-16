<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 14A — gift vouchers and café credit, in one table.
 *
 * They are the same object. Both are a code with a value, a window in which it
 * is good, and a single moment when somebody spends it. Two tables would mean
 * two redemption paths, and redemption is the one place where getting it wrong
 * means the same money is spent twice — so it gets written once, in one service,
 * with one lock.
 *
 * What differs is where they come from and what they buy, and both of those are
 * columns rather than structures:
 *
 *   gift         sold or given by the studio. Spent against a reservation at
 *                the payment stage. May be tied to one experience.
 *   cafe_credit  issued automatically when a space visit is paid for. Spent at
 *                the counter on food and drink. Never touches a reservation
 *                total — see the note in PaymentService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();

            // GIFT-2608-K4RT / CAFE-2608-K4RT. Read aloud at a counter, so the
            // same unambiguous alphabet as every other reference: no I, O, 0, 1.
            $table->string('code', 24)->unique();

            $table->string('type', 20);                 // gift | cafe_credit
            $table->string('status', 20)->default('active');

            $table->decimal('value', 12, 2);

            /*
             | The visit that earned this, for café credit. Null for a gift
             | voucher, which exists before anybody has booked anything.
             |
             | The unique pair below is what makes issuance safe to retry. A
             | payment callback can arrive twice — SSLCommerz sends both a
             | redirect and an IPN — and the second attempt to issue credit for
             | the same visit hits a constraint rather than quietly creating a
             | second coupon. MySQL permits repeated NULLs in a unique index, so
             | gift vouchers are unaffected.
             */
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();

            // Restricts a gift voucher to one experience. Null means any.
            $table->foreignId('workshop_id')->nullable()->constrained()->nullOnDelete();

            /*
             | Valid FROM the visit date, not from issue.
             |
             | Café credit is issued when payment lands, which can be weeks
             | before the visit. Counting the thirty days from then would hand
             | somebody a coupon that expires before they have even been. The
             | client asked for thirty days from the visit; this is the column
             | that means it.
             */
            $table->date('valid_from')->nullable();
            $table->date('expires_at')->nullable();

            $table->string('issued_to_name')->nullable();
            $table->string('issued_to_email')->nullable();
            $table->text('note')->nullable();

            // Single use, all or nothing — the client's decision. No balance
            // column: spending 180 of a 300 coupon forfeits the rest, and a
            // partial-redemption ledger would be a different feature.
            $table->timestamp('redeemed_at')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('redeemed_for_reservation_id')->nullable()
                ->constrained('reservations')->nullOnDelete();
            $table->text('redemption_note')->nullable();

            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            $table->unique(['reservation_id', 'type']);
            $table->index(['type', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};

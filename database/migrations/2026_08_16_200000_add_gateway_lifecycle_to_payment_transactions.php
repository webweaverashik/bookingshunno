<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 13 — room for attempts that never became receipts.
 *
 * 12B built this table around a settlement that had already happened, so
 * balance_after and received_at were both NOT NULL: money had arrived, and we
 * knew when and what was left. A gateway attempt inverts that. The row must
 * exist BEFORE the visitor is redirected — its reference becomes the tran_id
 * SSLCommerz quotes back to us, which is what makes a callback identifiable and
 * a repeated callback harmless — and at that moment nothing has been received.
 *
 * So both become nullable and stay null unless the attempt succeeds. A receipt
 * still means what it always did; a row with a null received_at is simply not
 * a receipt yet.
 *
 * validated_at is deliberately separate from received_at. The first is when
 * SSLCommerz told us the payment was good, the second is when we treat the
 * money as arrived. The same instant today — but in a settlement dispute,
 * "when did WE verify this" is a different and more useful question than "what
 * do our books say".
 *
 * REQUIRES doctrine/dbal on Laravel versions before 11 for ->change(). Laravel
 * 13 handles it natively, so nothing extra is needed here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->decimal('balance_after', 12, 2)->nullable()->change();
            $table->timestamp('received_at')->nullable()->change();

            // SSLCommerz's own identifiers, kept apart from external_reference
            // so a bank transaction number and a validation id never share a
            // column while meaning different things.
            $table->string('gateway_val_id', 100)->nullable()->after('gateway_payload');
            $table->string('gateway_bank_tran_id', 100)->nullable()->after('gateway_val_id');
            $table->string('gateway_card_type', 60)->nullable()->after('gateway_bank_tran_id');
            $table->timestamp('validated_at')->nullable()->after('received_at');

            // Why an attempt failed, in the gateway's words. For staff, not for
            // the visitor — "risk level 1" helps nobody at a checkout.
            $table->string('failure_reason', 255)->nullable()->after('note');

            // Asked on every run of the stale-attempt cleanup.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn([
                'gateway_val_id',
                'gateway_bank_tran_id',
                'gateway_card_type',
                'validated_at',
                'failure_reason',
            ]);
        });
    }
};

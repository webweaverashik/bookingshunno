<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 22 — SSLCommerz's own fraud assessment, stored where it can be seen.
 *
 * THE GAP THIS FILLS. The v4 documentation says, in the overview and again in
 * its sample code: "Sometime you will get Risk payments (In response you will
 * get risk properties, value will be 0 for safe, 1 for risky). It depends on
 * you to provide the service or not."
 *
 * It was arriving and being ignored. The whole validation payload was written
 * to gateway_payload, so risk_level was technically in the database — buried in
 * a JSON blob nobody queries, on a reservation that had already been confirmed
 * automatically and would be run without anyone ever seeing the flag.
 *
 * Promoted to real columns so the gateway log can show it, the payments report
 * can filter on it, and staff can be told before the evening rather than after
 * the chargeback.
 *
 * NOT a reason to refuse the money. The transaction settled: SSLCommerz says
 * VALID, the amount matches, funds moved. Rejecting it would leave a visitor
 * charged with no booking, which is worse than a booking somebody checks by
 * hand. The right response is a flag and a human.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            /*
             | Nullable, because it only exists for gateway transactions that
             | reached validation. A counter payment and a voucher settlement
             | have no risk assessment, and 0 would claim SSLCommerz had looked
             | at them and found them safe.
             */
            $table->unsignedTinyInteger('risk_level')->nullable()->after('gateway_card_type');
            $table->string('risk_title', 50)->nullable()->after('risk_level');

            // Partial by nature: almost every row is null or 0, so this index
            // is small and answers the only question anyone asks of it —
            // "show me the risky ones".
            $table->index('risk_level');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropIndex(['risk_level']);
            $table->dropColumn(['risk_level', 'risk_title']);
        });
    }
};

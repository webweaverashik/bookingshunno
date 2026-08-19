<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per settlement event.
 *
 * Brought forward from Phase 13 because the client requires a payslip for every
 * receipt, online or manual, and a payslip needs its own amount, method,
 * reference and timestamp. The payments table could not supply those: record()
 * wrote method and gateway_reference onto the payment row, so a second part
 * payment overwrote the first. 500 in cash followed by 1,000 by bKash became
 * "bKash, 1,500", which is wrong on a receipt and wrong in the books.
 *
 * That is a correctness fix, not a payslip convenience. The denormalised
 * columns stay on payments — they answer "how was this mostly paid" without a
 * join, for the register — but this table is the truth.
 *
 * SUCCESSFUL settlements only, for now. Phase 13 will also want to log failed
 * and abandoned gateway attempts; the status column is here so that can be
 * added without a migration, and nothing yet writes anything but 'success'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();

            // RCP-2608-K4RT. Printed at the top of the payslip and quoted back
            // by a visitor asking about a specific receipt, so it uses the same
            // unambiguous alphabet as the reservation and payment codes.
            $table->string('reference', 20)->unique();

            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();

            $table->string('channel', 20);              // manual | gateway
            $table->string('method', 40);               // cash, bkash, sslcommerz…
            $table->string('status', 20)->default('success');

            $table->decimal('amount', 12, 2);

            /*
             | Running total AFTER this receipt, snapshotted.
             |
             | The payslip has to state what was still owed at the moment it was
             | issued, and recomputing that later from the payment's current
             | figures would silently rewrite a receipt the visitor is holding.
             | A printed document must not change.
             */
            $table->decimal('balance_after', 12, 2);

            // Their reference, not ours: a bKash TrxID, a cheque number, the
            // gateway's val_id. Free text because it is somebody else's format.
            $table->string('external_reference', 100)->nullable();
            $table->json('gateway_payload')->nullable();

            $table->text('note')->nullable();

            $table->timestamp('received_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['payment_id', 'received_at']);
            $table->index(['channel', 'received_at']);
        });

        /*
        | Backfill.
        |
        | Anything already part- or fully-paid under 12A has money recorded but
        | no receipt behind it, and would render a payments detail screen with a
        | figure and no payslip. One row per such payment reconstructs the
        | receipt from the denormalised columns — which is exactly right,
        | because under 12A there could only ever have been one.
        |
        | Raw queries rather than the models on purpose: a migration that boots
        | Eloquent breaks the day someone renames a cast.
        */
        $paid = DB::table('payments')
            ->where('amount_paid', '>', 0)
            ->get(['id', 'amount_paid', 'amount_due', 'method', 'gateway_reference', 'paid_at', 'recorded_by', 'created_at']);

        foreach ($paid as $payment) {
            DB::table('payment_transactions')->insert([
                'reference'          => $this->backfillReference(),
                'payment_id'         => $payment->id,
                'channel'            => $payment->method === 'sslcommerz' ? 'gateway' : 'manual',
                'method'             => $payment->method ?? 'other',
                'status'             => 'success',
                'amount'             => $payment->amount_paid,
                'balance_after'      => max(0, (float) $payment->amount_due - (float) $payment->amount_paid),
                'external_reference' => $payment->gateway_reference,
                'note'               => 'Reconstructed when receipts were introduced.',
                'received_at'        => $payment->paid_at ?? $payment->created_at,
                'recorded_by'        => $payment->recorded_by,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }
    }

    private function backfillReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = 'RCP-' . now()->format('ym') . '-' . $suffix;
        } while (DB::table('payment_transactions')->where('reference', $code)->exists());

        return $code;
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};

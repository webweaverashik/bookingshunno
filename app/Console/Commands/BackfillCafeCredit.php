<?php

namespace App\Console\Commands;

use App\Enums\Reservation\ReservationStatus;
use App\Enums\Voucher\VoucherType;
use App\Models\Reservation\Reservation;
use App\Models\Voucher\Voucher;
use App\Services\Setting\SettingsRepository;
use App\Services\Voucher\VoucherService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Issue the café credit that never got issued.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS EXISTS
 * ---------------------------------------------------------------------------
 * `cafe_credit_per_person` was missing from WorkshopService::attributes(), so
 * every attempt to set it from the workshop form was silently discarded and the
 * column stayed at its default of zero on every row. issueCafeCredit() then did
 * exactly what it is supposed to do with a zero figure — nothing — and said
 * nothing about it, because at the time neither outcome wrote to the history.
 *
 * Phase 35A fixed the form. Phase 36 made the skip audible. Neither goes back
 * and pays what was already owed, which is what this does. Paid visits, real
 * coupons, sent late.
 *
 * NOT SCHEDULED. It runs once, by hand, after the studio has set the per-person
 * figure on the workshops that carry it — and it is worth running --dry-run
 * first, because the figure it multiplies by is whatever is on the workshop
 * TODAY, not what was in force at the time of the visit. There is no historical
 * record of the intended amount; the column was zero throughout. If the studio
 * sets 100 today, a visit from March is credited at 100.
 *
 * ---------------------------------------------------------------------------
 * THE EXPIRY PROBLEM, AND WHY IT IS A FLAG RATHER THAN A DECISION
 * ---------------------------------------------------------------------------
 * Café credit runs from the VISIT date, not from the date of issue — correct
 * for the normal case, where the coupon is minted weeks before somebody turns
 * up. Applied to a backfill it produces coupons that were born expired: a visit
 * in March plus thirty days is a code that cannot be spent.
 *
 * Whether to extend those is a decision about money and about what the studio
 * is prepared to honour, and §25 of the brief says an ambiguity touching either
 * gets identified rather than invented. So:
 *
 *   default          coupons whose window has already closed are SKIPPED and
 *                    listed, not issued. Nothing is quietly given away and
 *                    nothing useless is emailed.
 *
 *   --from-today     issues them anyway, with the window counted from today.
 *                    An explicit instruction from somebody who has decided.
 *
 * Coupons still inside their original window are issued either way — those were
 * always owed and are still spendable.
 */
class BackfillCafeCredit extends Command
{
    protected $signature = 'shunno:backfill-cafe-credit
                            {--dry-run : List what would be issued without issuing or emailing anything}
                            {--from-today : Also issue coupons whose original window has passed, counted from today}
                            {--reservation= : Restrict to one reservation reference, e.g. SHN-2608-A7K3}';

    protected $description = 'Issue café credit for already-paid visits that never received it.';

    public function handle(VoucherService $vouchers, SettingsRepository $settings): int
    {
        $dryRun    = (bool) $this->option('dry-run');
        $fromToday = (bool) $this->option('from-today');
        $days      = (int) $settings->get('cafe_credit.validity_days', 30) ?: 30;

        $candidates = $this->candidates();

        if ($candidates->isEmpty()) {
            $this->info('Nothing to backfill. Every paid visit on a credit-earning experience already has its coupon.');

            return self::SUCCESS;
        }

        $this->line("Found {$candidates->count()} paid " . str('visit')->plural($candidates->count()) . ' with no café credit.');
        $this->newLine();

        $issued  = 0;
        $skipped = 0;
        $value   = 0.0;

        foreach ($candidates as $reservation) {
            $perPerson = $this->perPerson($reservation);
            $amount    = round($perPerson * max(1, (int) $reservation->participants), 2);

            /*
             | Recomputed here purely to decide whether it has expired and to
             | print a figure in the dry run. The authoritative calculation
             | stays inside VoucherService — this must never become a second
             | place that decides what a coupon is worth.
             */
            $visit   = CarbonImmutable::parse($reservation->reserved_date);
            $lapsed  = $visit->addDays($days)->endOfDay()->isPast();

            $label = sprintf(
                '%s  %s  %d %s  BDT %s',
                str_pad($reservation->reference_code, 16),
                $visit->format('j M Y'),
                $reservation->participants,
                str('guest')->plural($reservation->participants),
                number_format($amount),
            );

            if ($lapsed && ! $fromToday) {
                $this->warn("  skip   {$label}  — window closed " . $visit->addDays($days)->diffForHumans());
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line("  would  {$label}" . ($lapsed ? '  — re-dated from today' : ''));
                $issued++;
                $value += $amount;

                continue;
            }

            $voucher = $vouchers->issueCafeCredit(
                $reservation,
                null,

                /*
                 | Null means "use the visit date", which is the normal rule and
                 | the right answer for a coupon still inside its window. Today
                 | is passed only for the lapsed ones, and only because somebody
                 | asked for --from-today.
                 */
                $lapsed ? CarbonImmutable::today() : null,

                $lapsed
                    ? 'Issued by backfill, re-dated from ' . CarbonImmutable::today()->format('j M Y') . '.'
                    : 'Issued by backfill — payment settled earlier.',
            );

            if (! $voucher) {
                // issueCafeCredit() found nothing to issue after all. Should not
                // happen, since the query already filtered on a non-zero figure,
                // but a workshop edited between the query and this line would do
                // it. Reported rather than swallowed.
                $this->warn("  none   {$label}  — the experience carries no credit");
                $skipped++;

                continue;
            }

            $this->info("  issued {$label}  {$voucher->code}" . ($lapsed ? '  (re-dated)' : ''));
            $issued++;
            $value += $amount;
        }

        $this->newLine();

        if ($dryRun) {
            $this->line(sprintf(
                'Dry run. %d coupons worth BDT %s would be issued and emailed; %d skipped.',
                $issued,
                number_format($value),
                $skipped,
            ));

            if ($skipped > 0 && ! $fromToday) {
                $this->line('Re-run with --from-today to issue the skipped ones with a fresh window.');
            }

            return self::SUCCESS;
        }

        $this->line(sprintf(
            'Issued %d coupons worth BDT %s. %d skipped. Emails are queued — the worker sends them.',
            $issued,
            number_format($value),
            $skipped,
        ));

        return self::SUCCESS;
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Paid visits, on a credit-earning experience, with no coupon.
     *
     * CONFIRMED AND COMPLETED ONLY. Both are reached solely by a settlement, so
     * this cannot mint a coupon for a booking nobody paid for — which is the
     * whole reason credit is issued on payment rather than on approval.
     *
     * NoShow is deliberately absent. Somebody who paid and did not turn up has
     * arguably earned nothing, and arguably earned it just as much as anyone
     * else; it is the studio's call, not this command's, and it is a short list
     * to handle by hand in Voucher Management either way.
     *
     * @return Collection<int,Reservation>
     */
    private function candidates(): Collection
    {
        $reference = $this->option('reservation');

        return Reservation::query()
            ->whereIn('status', [
                ReservationStatus::Confirmed->value,
                ReservationStatus::Completed->value,
            ])

            // The workshop carries credit TODAY. A row still sitting at zero is
            // not a missing coupon, it is an experience that earns none.
            ->whereHas('items.workshop', fn ($q) => $q->where('cafe_credit_per_person', '>', 0))

            // Nothing already issued. Mirrors the unique (reservation_id, type)
            // pair that guards issueCafeCredit() against a double settlement —
            // that constraint would catch a duplicate anyway, but catching it
            // here means the run reports accurate numbers instead of counting
            // rows it did not create.
            ->whereNotIn('id', Voucher::query()
                ->where('type', VoucherType::CafeCredit->value)
                ->whereNotNull('reservation_id')
                ->select('reservation_id'))

            ->when($reference, fn ($q) => $q->where('reference_code', $reference))
            ->with(['items.workshop', 'user'])
            ->orderBy('reserved_date')
            ->get();
    }

    /**
     * The highest per-head figure among the booked items.
     *
     * Duplicates VoucherService::cafeCreditPerPerson(), which is private and
     * should stay that way. Used ONLY to print a figure in the dry run and to
     * decide nothing — the coupon's actual value is whatever the service
     * computes when it writes the row.
     */
    private function perPerson(Reservation $reservation): float
    {
        return (float) $reservation->items
            ->map(fn ($item) => (float) ($item->workshop?->cafe_credit_per_person ?? 0))
            ->max();
    }
}

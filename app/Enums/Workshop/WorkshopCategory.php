<?php

namespace App\Enums\Workshop;

/**
 * Groupings taken from the printed workshop menu.
 */
enum WorkshopCategory: string
{
    case Express    = 'express';
    case Immersive  = 'immersive';
    case Mindful    = 'mindful';
    case Chalantika = 'chalantika';
    case Other      = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Express    => 'Express',
            self::Immersive  => 'Immersive',
            self::Mindful    => 'Mindful',
            self::Chalantika => 'Chalantika special',
            self::Other      => 'Other purposes',
        };
    }

    /**
     * Whether this kind of booking can earn a café coupon.
     *
     * THE CLIENT'S RULE, in one place. Café credit is a thank-you for time
     * spent in the space without a session — the "Other purposes" bookings: a
     * plain visit, an exhibition, quiet work. A paid workshop already includes
     * materials and tuition and earns nothing on top.
     *
     * A method on the enum rather than the string 'other' compared in three
     * files. Four things ask this question — the form request that decides what
     * to store, the modal that decides what to show, the migration that clears
     * what should never have been set, and any future report — and the answer
     * has to be the same in all of them. If the studio later adds a second
     * credit-earning category, this match is the only edit.
     *
     * DELIBERATELY NOT a column on workshops. The figure is per workshop; the
     * question of whether the figure is allowed at all is per category, and
     * mixing the two would let somebody set 50 BDT on a clay session by
     * changing one row.
     */
    public function carriesCafeCredit(): bool
    {
        return $this === self::Other;
    }

    /**
     * The values that carry café credit, for the browser.
     *
     * Rendered into a data attribute on the workshop form so the JavaScript
     * that shows and hides the field reads the rule from here rather than
     * repeating it. The server does not trust the answer — see
     * WorkshopRequest::prepareForValidation(), which zeroes the figure for any
     * other category whatever the browser sent.
     *
     * @return array<int,string>
     */
    public static function creditBearing(): array
    {
        return array_map(
            fn (self $case) => $case->value,
            array_values(array_filter(self::cases(), fn (self $case) => $case->carriesCafeCredit())),
        );
    }

    /**
     * Metronic badge class per category, so the admin table reads at a glance.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Express    => 'badge-light-primary',
            self::Immersive  => 'badge-light-info',
            self::Mindful    => 'badge-light-success',
            self::Chalantika => 'badge-light-warning',
            self::Other      => 'badge-light-secondary',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * For <select> rendering and for the `in:` validation rule.
     *
     * @return array<string,string>  ['express' => 'Express', ...]
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}

<?php

namespace App\Enums;

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

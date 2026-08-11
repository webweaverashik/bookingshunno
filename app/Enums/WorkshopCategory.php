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
}

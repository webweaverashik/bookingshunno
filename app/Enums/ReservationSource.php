<?php

namespace App\Enums;

enum ReservationSource: string
{
    case Web              = 'web';
    case Admin            = 'admin';
    case GoogleFormImport = 'google_form_import';

    public function label(): string
    {
        return match ($this) {
            self::Web              => 'Website',
            self::Admin            => 'Entered by staff',
            self::GoogleFormImport => 'Imported from Google Form',
        };
    }
}

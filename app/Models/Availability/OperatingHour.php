<?php

namespace App\Models\Availability;

use Illuminate\Database\Eloquent\Model;

class OperatingHour extends Model
{
    protected $fillable = ['day_of_week', 'opens_at', 'closes_at', 'is_closed'];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_closed'   => 'boolean',
        ];
    }
}

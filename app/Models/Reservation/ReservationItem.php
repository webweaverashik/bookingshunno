<?php

namespace App\Models\Reservation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Workshop\Workshop;

class ReservationItem extends Model
{
    protected $fillable = [
        'reservation_id', 'workshop_id', 'title_snapshot',
        'unit_price', 'quantity', 'line_total', 'duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'quantity'   => 'integer',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** Nullable: a workshop can be soft-deleted long after the visit happened. */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }
}

<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationStatusHistory extends Model
{
    public const UPDATED_AT = null;   // append-only: rows are never edited

    protected $fillable = ['reservation_id', 'from_status', 'to_status', 'changed_by', 'note'];

    protected function casts(): array
    {
        return [
            'from_status' => ReservationStatus::class,
            'to_status'   => ReservationStatus::class,
            'created_at'  => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function actorName(): string
    {
        return $this->changedBy?->name ?? 'System';
    }
}

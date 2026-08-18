<?php

namespace App\Models\Reservation;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Workshop\Workshop;

class VisitPurpose extends Model
{
    protected $fillable = [
        'slug', 'name', 'description', 'is_chargeable',
        'is_invitation_only', 'default_workshop_id', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_chargeable'      => 'boolean',
            'is_invitation_only' => 'boolean',
            'is_active'          => 'boolean',
        ];
    }

    public function defaultWorkshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class, 'default_workshop_id');
    }

    public function reservations(): BelongsToMany
    {
        return $this->belongsToMany(Reservation::class, 'reservation_purpose');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}

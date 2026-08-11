<?php

namespace App\Models;

use App\Enums\WorkshopCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workshop extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'slug', 'title', 'medium', 'category', 'short_description', 'description',
        'image_path', 'gallery', 'price', 'price_basis', 'duration_minutes',
        'min_participants', 'max_participants', 'materials_included',
        'requires_experience', 'is_active', 'is_featured', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'category'            => WorkshopCategory::class,
            'gallery'             => 'array',
            'price'               => 'decimal:2',
            'materials_included'  => 'boolean',
            'requires_experience' => 'boolean',
            'is_active'           => 'boolean',
            'is_featured'         => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function reservationItems(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Shortest session first — the landing page reads as a time spectrum. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('duration_minutes')->orderBy('sort_order')->orderBy('price');
    }

    public function durationHours(): float
    {
        return round($this->duration_minutes / 60, 2);
    }
}

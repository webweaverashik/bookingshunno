<?php
namespace App\Models;

use App\Enums\WorkshopCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Workshop extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The public menu is read on every landing-page hit and again by the
     * reservation popup and the slot builder. ExperienceCatalogue was a static
     * array and cost nothing; this keeps that property. Every write in
     * WorkshopService calls forgetMenu().
     */
    public const MENU_CACHE_KEY = 'shunno.workshops.menu';

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

    /**
     * Public URLs use the slug. Admin routes bind on {workshop:id} instead, so
     * renaming a workshop never invalidates an admin link mid-edit.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function reservationItems(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Shortest session first — the landing page reads as a time spectrum. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('duration_minutes')->orderBy('sort_order')->orderBy('price');
    }

    /** Admin table order: the curator's own sequence, not the time spectrum. */
    public function scopeAdminOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('duration_minutes')->orderBy('title');
    }

    /*
    |--------------------------------------------------------------------------
    | The public menu
    |--------------------------------------------------------------------------
    */

    protected static ?Collection $menu = null;

    /** @return Collection<int,static> */
    public static function menu(): Collection
    {
        return static::$menu ??= static::query()->active()->ordered()->get();
    }

    public static function forgetMenu(): void
    {
        static::$menu = null;
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */

    /**
     * Handles both storage paths (admin uploads) and the repo-shipped images
     * seeded from the printed menu, so replacing one image does not require
     * moving the other six.
     */
    public function imageUrl(): ?string
    {
        $path = $this->image_path;

        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (Str::startsWith($path, 'workshops/')) {
            return Storage::disk('public')->url($path);
        }

        return asset($path); // legacy: public/img/shunno/exp-*.jpg
    }

    public function categoryLabel(): string
    {
        return $this->category?->label() ?? '';
    }

    public function durationHours(): float
    {
        return round($this->duration_minutes / 60, 2);
    }

    /** "2 hrs", "1 hr 30 min", "45 min" */
    public function durationLabel(): string
    {
        $hours   = intdiv($this->duration_minutes, 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours && $minutes) {
            return $hours . ' ' . Str::plural('hr', $hours) . ' ' . $minutes . ' min';
        }

        if ($hours) {
            return $hours . ' ' . Str::plural('hr', $hours);
        }

        return $minutes . ' min';
    }

    /**
     * Ticks for the duration meter on the public card. Four is the longest
     * session on the printed menu; anything longer simply fills the bar.
     */
    public function durationTicks(int $max = 4): int
    {
        return min($max, max(1, (int) ceil($this->duration_minutes / 60)));
    }

    /*
    |--------------------------------------------------------------------------
    | Guards
    |--------------------------------------------------------------------------
    */

    /**
     * Line items snapshot the title and price, so history survives a delete —
     * but a workshop with reservations against it should be deactivated, not
     * removed, or the admin loses the ability to filter reports by it.
     */
    public function hasReservations(): bool
    {
        return $this->reservationItems()->exists();
    }
}

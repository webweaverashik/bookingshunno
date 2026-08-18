<?php

namespace App\Models\Auth;

use App\Enums\Reservation\ReservationSource;
use App\Models\Reservation\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles, Notifiable, SoftDeletes;

    protected $guard_name = 'web';

    protected $fillable = ['name', 'email', 'phone', 'whatsapp', 'is_active', 'source', 'photo_url', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'source' => ReservationSource::class,
            'total_reservations' => 'integer',
            'last_reservation_at' => 'datetime',
        ];
    }

    public const ROLE_VISITOR = 'Visitor';

    /*
    |--------------------------------------------------------------------------
    | Role Helpers
    |--------------------------------------------------------------------------
    */
    public function isAdmin(): bool
    {
        return $this->hasRole('Admin');
    }

    public function isManager(): bool
    {
        return $this->hasRole('Manager');
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(['Admin', 'Manager']);
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class)->latest('reserved_date');
    }

    public function loginActivities()
    {
        return $this->hasMany(LoginActivity::class, 'user_id');
    }

    public function latestLoginActivity()
    {
        return $this->hasOne(LoginActivity::class)
            ->latestOfMany()
            ->select('login_activities.*');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Visitors are users holding the Visitor role and nothing else. Scoping on
     * the role rather than on "not staff" means a future role cannot silently
     * fall into the visitor list.
     */
    public function scopeVisitors(Builder $query): Builder
    {
        return $query->role(self::ROLE_VISITOR);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        // Bengali names are stored utf8mb4; LIKE handles them correctly on the
        // collation this database uses.
        return $query->where(function (Builder $inner) use ($term) {
            $inner->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('whatsapp', 'like', "%{$term}%");
        });
    }

    public function isVisitor(): bool
    {
        return $this->hasRole(self::ROLE_VISITOR);
    }

    /**
     * Whether this visitor ever chose a password. resolveVisitor() creates
     * accounts with a random one so the column is never null, which means a
     * null password cannot be used to tell a real account from a placeholder —
     * the reservation count is the honest signal, and Phase 15 will need this
     * to decide whether to offer a sign-in or a set-a-password link.
     */
    public function hasSetPassword(): bool
    {
        return (bool) $this->email_verified_at;
    }
}

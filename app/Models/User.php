<?php

namespace App\Models;

use App\Enums\ReservationSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Staff and visitors share one table and one guard.
 *
 * spatie/laravel-permission is well behaved with a single guard and a known
 * source of subtle bugs across several, and a shared table means the OTP login
 * screens carried over from the BIDA template work for visitors unchanged.
 *
 * NOTE: explicit $fillable rather than extending BaseModel. BaseModel sets
 * $guarded = [], which is defensible in an internal system but not here — this
 * model is written from a public, unauthenticated form.
 */
class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use HasRoles;
    use SoftDeletes;

    public const ROLE_ADMIN   = 'Admin';
    public const ROLE_MANAGER = 'Manager';
    public const ROLE_VISITOR = 'Visitor';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'whatsapp',
        'password',
        'password_set_at',
        'is_active',
        'source',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'   => 'datetime',
            'password'            => 'hashed',
            'password_set_at'     => 'datetime',
            'last_reservation_at' => 'datetime',
            'last_login_at'       => 'datetime',
            'is_active'           => 'boolean',
            'source'              => ReservationSource::class,
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** A visitor who has never chosen a password signs in by OTP only. */
    public function hasUsablePassword(): bool
    {
        return $this->password_set_at !== null;
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole([self::ROLE_ADMIN, self::ROLE_MANAGER]);
    }
}

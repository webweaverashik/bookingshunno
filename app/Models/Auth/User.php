<?php
namespace App\Models\Auth;

use App\Models\Auth\LoginActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles, SoftDeletes, Notifiable;

    protected $guard_name = 'web';

    protected $fillable = ['name', 'email', 'phone', 'whatsapp', 'is_active', 'photo_url', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

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

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */
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
}

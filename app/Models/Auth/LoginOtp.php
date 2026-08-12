<?php
namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

class LoginOtp extends Model
{
    protected $fillable = ['user_id', 'code', 'expires_at', 'attempts', 'last_sent_at'];

    protected function casts(): array
    {
        return [
            'expires_at'   => 'datetime',
            'last_sent_at' => 'datetime',
            'attempts'     => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}

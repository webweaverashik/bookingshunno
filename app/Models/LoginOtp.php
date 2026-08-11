<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Namespace note: flattened from App\Models\Auth\LoginOtp to App\Models\LoginOtp
 * to match the Laravel skeleton, which ships App\Models\User. Nothing else in
 * the codebase follows a Models/Auth/ convention.
 */
class LoginOtp extends Model
{
    protected $fillable = [
        'user_id', 'code', 'expires_at', 'attempts',
        'total_attempts', 'resend_count', 'last_sent_at',
    ];

    protected $hidden = ['code'];

    /**
     * Casts matter here: secondsUntilResend() calls ->getTimestamp() on
     * last_sent_at, which fatals on a raw string.
     */
    protected function casts(): array
    {
        return [
            'expires_at'     => 'datetime',
            'last_sent_at'   => 'datetime',
            'attempts'       => 'integer',
            'total_attempts' => 'integer',
            'resend_count'   => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}

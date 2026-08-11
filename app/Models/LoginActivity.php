<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class LoginActivity extends Model
{
    public const UPDATED_AT = null;   // append-only

    protected $fillable = ['user_id', 'email', 'event', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $event, Request $request, ?User $user = null, ?string $email = null): void
    {
        self::create([
            'user_id'    => $user?->id,
            'email'      => $email ?? $user?->email,
            'event'      => $event,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);
    }
}

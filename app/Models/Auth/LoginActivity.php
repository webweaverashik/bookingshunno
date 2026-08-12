<?php
namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginActivity extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'ip_address', 'user_agent', 'device'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

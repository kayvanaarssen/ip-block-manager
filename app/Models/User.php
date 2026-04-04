<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;

#[Fillable(['name', 'email', 'password', 'theme_preference', 'telegram_user_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasPasskeys
{
    use HasFactory, Notifiable, InteractsWithPasskeys;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function blockedIps()
    {
        return $this->hasMany(BlockedIp::class, 'blocked_by');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }
}

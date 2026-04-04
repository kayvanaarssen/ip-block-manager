<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class BlockedIp extends Model
{
    use HasUlids;

    protected $fillable = ['ip_address', 'reason', 'blocked_by', 'geo_data'];

    protected function casts(): array
    {
        return [
            'geo_data' => 'array',
        ];
    }

    public function servers()
    {
        return $this->belongsToMany(Server::class, 'blocked_ip_server')
            ->withPivot(['status', 'error_message', 'blocked_at', 'unblocked_at'])
            ->withTimestamps();
    }

    public function blockedByUser()
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function sshTaskLogs()
    {
        return $this->hasMany(SshTaskLog::class);
    }

    public function isFullyBlocked(): bool
    {
        return $this->servers()->wherePivot('status', '!=', 'blocked')->doesntExist();
    }

    public function hasFailures(): bool
    {
        return $this->servers()->wherePivot('status', 'failed')->exists();
    }
}

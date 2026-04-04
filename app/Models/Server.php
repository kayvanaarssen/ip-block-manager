<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    use HasUlids;

    protected $fillable = [
        'name', 'host', 'port', 'ssh_user', 'ssh_private_key',
        'ssh_fingerprint', 'script_installed', 'is_active',
    ];

    protected $casts = [
        'ssh_private_key' => 'encrypted',
        'is_active' => 'boolean',
        'script_installed' => 'boolean',
        'port' => 'integer',
        'last_connected_at' => 'datetime',
    ];

    protected $hidden = ['ssh_private_key'];

    public function blockedIps()
    {
        return $this->belongsToMany(BlockedIp::class, 'blocked_ip_server')
            ->withPivot(['status', 'error_message', 'blocked_at', 'unblocked_at'])
            ->withTimestamps();
    }

    public function sshTaskLogs()
    {
        return $this->hasMany(SshTaskLog::class);
    }

    public function activeBlockedIps()
    {
        return $this->blockedIps()->wherePivot('status', 'blocked');
    }
}

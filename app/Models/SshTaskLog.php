<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class SshTaskLog extends Model
{
    use HasUlids;

    protected $fillable = [
        'blocked_ip_id', 'server_id', 'command', 'status',
        'output', 'error', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function blockedIp()
    {
        return $this->belongsTo(BlockedIp::class);
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }
}

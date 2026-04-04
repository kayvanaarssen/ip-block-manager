<?php

namespace App\Services;

use App\Jobs\ExecuteSshBlockJob;
use App\Jobs\ExecuteSshUnblockJob;
use App\Models\BlockedIp;
use App\Models\Server;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class IpBlockService
{
    public function __construct(
        private AuditService $audit,
    ) {}

    public function blockOnServers(string $ip, Collection $servers, ?string $reason = null): BlockedIp
    {
        $blockedIp = BlockedIp::create([
            'ip_address' => $ip,
            'reason' => $reason,
            'blocked_by' => Auth::id(),
        ]);

        foreach ($servers as $server) {
            $blockedIp->servers()->attach($server->id, ['id' => Str::ulid()->toBase32(), 'status' => 'pending']);
            ExecuteSshBlockJob::dispatch($blockedIp, $server);
        }

        $this->audit->log('block_ip', $blockedIp, [
            'ip_address' => $ip,
            'server_ids' => $servers->pluck('id')->toArray(),
            'server_names' => $servers->pluck('name')->toArray(),
            'reason' => $reason,
        ]);

        return $blockedIp;
    }

    public function unblockFromServers(BlockedIp $blockedIp, ?Collection $servers = null): void
    {
        $servers = $servers ?? $blockedIp->servers()->wherePivot('status', 'blocked')->get();

        foreach ($servers as $server) {
            $blockedIp->servers()->updateExistingPivot($server->id, ['status' => 'unblocking']);
            ExecuteSshUnblockJob::dispatch($blockedIp, $server);
        }

        $this->audit->log('unblock_ip', $blockedIp, [
            'ip_address' => $blockedIp->ip_address,
            'server_ids' => $servers->pluck('id')->toArray(),
            'server_names' => $servers->pluck('name')->toArray(),
        ]);
    }

    public function unblockFromAllServers(BlockedIp $blockedIp): void
    {
        $this->unblockFromServers($blockedIp);
    }
}

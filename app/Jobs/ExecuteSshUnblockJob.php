<?php

namespace App\Jobs;

use App\Models\BlockedIp;
use App\Models\Server;
use App\Models\SshTaskLog;
use App\Services\SshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteSshUnblockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public BlockedIp $blockedIp,
        public Server $server,
    ) {}

    public function handle(SshService $sshService): void
    {
        $taskLog = SshTaskLog::create([
            'blocked_ip_id' => $this->blockedIp->id,
            'server_id' => $this->server->id,
            'command' => 'unblock',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $result = $sshService->unblockIp($this->server, $this->blockedIp->ip_address);

            $taskLog->update([
                'status' => $result['success'] ? 'completed' : 'failed',
                'output' => $result['output'],
                'error' => $result['success'] ? null : $result['output'],
                'completed_at' => now(),
            ]);

            $this->blockedIp->servers()->updateExistingPivot(
                $this->server->id,
                [
                    'status' => $result['success'] ? 'unblocked' : 'failed',
                    'error_message' => $result['success'] ? null : $result['output'],
                    'unblocked_at' => $result['success'] ? now() : null,
                ]
            );

            $this->server->update(['last_connected_at' => now()]);

        } catch (\Throwable $e) {
            $taskLog->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            $this->blockedIp->servers()->updateExistingPivot(
                $this->server->id,
                [
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }
}

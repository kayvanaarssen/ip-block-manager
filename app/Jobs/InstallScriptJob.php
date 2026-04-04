<?php

namespace App\Jobs;

use App\Models\Server;
use App\Models\SshTaskLog;
use App\Services\SshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class InstallScriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public Server $server,
    ) {}

    public function handle(SshService $sshService): void
    {
        $taskLog = SshTaskLog::create([
            'server_id' => $this->server->id,
            'command' => 'install',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $success = $sshService->installScript($this->server);

            $taskLog->update([
                'status' => $success ? 'completed' : 'failed',
                'output' => $success ? 'Script installed successfully' : 'Script installation failed',
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $taskLog->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }
}

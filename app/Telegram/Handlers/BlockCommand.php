<?php

namespace App\Telegram\Handlers;

use App\Models\BlockedIp;
use App\Models\Server;
use App\Rules\ValidIpOrCidr;
use App\Services\IpBlockService;
use Illuminate\Support\Facades\Validator;
use SergiX44\Nutgram\Nutgram;

class BlockCommand
{
    public function __construct(
        private IpBlockService $ipBlockService,
    ) {}

    public function __invoke(Nutgram $bot, string $ip): void
    {
        $ip = trim($ip);

        // Parse reason from the full message text (everything after the IP)
        $text = $bot->message()?->text ?? '';
        $reason = null;
        if (preg_match('/\/block\s+\S+\s+(.+)$/s', $text, $matches)) {
            $reason = trim($matches[1]) ?: null;
        }

        // Validate the IP
        $validator = Validator::make(['ip' => $ip], [
            'ip' => ['required', new ValidIpOrCidr],
        ]);

        if ($validator->fails()) {
            $bot->sendMessage("Invalid IP address: {$ip}");
            return;
        }

        // Check if already blocked
        $existing = BlockedIp::where('ip_address', $ip)->first();
        if ($existing) {
            $blockedCount = $existing->servers()->wherePivot('status', 'blocked')->count();
            if ($blockedCount > 0) {
                $bot->sendMessage("IP {$ip} is already blocked on {$blockedCount} server(s).\n\nUse /status {$ip} for details.");
                return;
            }

            // Re-block on all servers
            $servers = Server::where('is_active', true)->get();
            if ($servers->isEmpty()) {
                $bot->sendMessage("No active servers found.");
                return;
            }

            $this->ipBlockService->reblockOnServers($existing, $servers);
            $bot->sendMessage("Re-blocking {$ip} on {$servers->count()} server(s)...\n\nUse /status {$ip} to check progress.");
            return;
        }

        $servers = Server::where('is_active', true)->get();
        if ($servers->isEmpty()) {
            $bot->sendMessage("No active servers found.");
            return;
        }

        $this->ipBlockService->blockOnServers($ip, $servers, $reason);

        $serverNames = $servers->pluck('name')->join(', ');
        $msg = "Blocking {$ip} on {$servers->count()} server(s): {$serverNames}";
        if ($reason) {
            $msg .= "\nReason: {$reason}";
        }
        $msg .= "\n\nUse /status {$ip} to check progress.";

        $bot->sendMessage($msg);
    }
}

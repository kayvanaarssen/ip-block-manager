<?php

namespace App\Telegram\Handlers;

use App\Models\Server;
use SergiX44\Nutgram\Nutgram;

class ServersCommand
{
    public function __invoke(Nutgram $bot): void
    {
        $servers = Server::where('is_active', true)
            ->withCount(['blockedIps' => fn ($q) => $q->where('blocked_ip_server.status', 'blocked')])
            ->get();

        if ($servers->isEmpty()) {
            $bot->sendMessage("No active servers found.");
            return;
        }

        $msg = "Active Servers ({$servers->count()}):\n\n";

        foreach ($servers as $server) {
            $connected = $server->last_connected_at
                ? $server->last_connected_at->diffForHumans()
                : 'never';

            $msg .= "{$server->name}\n";
            $msg .= "  Host: {$server->host}:{$server->port}\n";
            $msg .= "  Blocked IPs: {$server->blocked_ips_count}\n";
            $msg .= "  Script: ".($server->script_installed ? "v{$server->script_version}" : 'not installed')."\n";
            $msg .= "  Last connected: {$connected}\n\n";
        }

        $bot->sendMessage($msg);
    }
}

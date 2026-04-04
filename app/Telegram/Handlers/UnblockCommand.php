<?php

namespace App\Telegram\Handlers;

use App\Models\BlockedIp;
use App\Rules\ValidIpOrCidr;
use App\Services\IpBlockService;
use Illuminate\Support\Facades\Validator;
use SergiX44\Nutgram\Nutgram;

class UnblockCommand
{
    public function __construct(
        private IpBlockService $ipBlockService,
    ) {}

    public function __invoke(Nutgram $bot, string $ip): void
    {
        $ip = trim($ip);

        $validator = Validator::make(['ip' => $ip], [
            'ip' => ['required', new ValidIpOrCidr],
        ]);

        if ($validator->fails()) {
            $bot->sendMessage("Invalid IP address: {$ip}");
            return;
        }

        $blockedIp = BlockedIp::where('ip_address', $ip)->first();

        if (! $blockedIp) {
            $bot->sendMessage("IP {$ip} is not in the block list.");
            return;
        }

        $blockedServers = $blockedIp->servers()->wherePivot('status', 'blocked')->get();

        if ($blockedServers->isEmpty()) {
            $bot->sendMessage("IP {$ip} is not actively blocked on any servers.");
            return;
        }

        $this->ipBlockService->unblockFromServers($blockedIp, $blockedServers);

        $serverNames = $blockedServers->pluck('name')->join(', ');
        $bot->sendMessage("Unblocking {$ip} from {$blockedServers->count()} server(s): {$serverNames}\n\nUse /status {$ip} to check progress.");
    }
}

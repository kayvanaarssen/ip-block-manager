<?php

namespace App\Telegram\Handlers;

use App\Models\BlockedIp;
use App\Rules\ValidIpOrCidr;
use Illuminate\Support\Facades\Validator;
use SergiX44\Nutgram\Nutgram;

class StatusCommand
{
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

        $blockedIp = BlockedIp::where('ip_address', $ip)->with(['servers', 'blockedByUser'])->first();

        if (! $blockedIp) {
            $bot->sendMessage("IP {$ip} is not in the block list.");
            return;
        }

        $statusIcons = [
            'blocked' => "\xE2\x9B\x94",     // no entry
            'pending' => "\xE2\x8F\xB3",      // hourglass
            'blocking' => "\xE2\x8F\xB3",     // hourglass
            'unblocking' => "\xE2\x8F\xB3",   // hourglass
            'failed' => "\xE2\x9D\x8C",       // red X
            'unblocked' => "\xE2\x9C\x85",    // green check
        ];

        $msg = "IP: {$blockedIp->ip_address}\n";

        if ($blockedIp->reason) {
            $msg .= "Reason: {$blockedIp->reason}\n";
        }

        if ($blockedIp->blockedByUser) {
            $msg .= "Blocked by: {$blockedIp->blockedByUser->name}\n";
        }

        $msg .= "Created: {$blockedIp->created_at->diffForHumans()}\n\n";

        if ($blockedIp->servers->isEmpty()) {
            $msg .= "No servers attached.";
        } else {
            $msg .= "Servers:\n";
            foreach ($blockedIp->servers as $server) {
                $icon = $statusIcons[$server->pivot->status] ?? "\xE2\x9D\x93"; // question mark
                $msg .= "{$icon} {$server->name} - {$server->pivot->status}";
                if ($server->pivot->error_message) {
                    $msg .= " ({$server->pivot->error_message})";
                }
                $msg .= "\n";
            }
        }

        $bot->sendMessage($msg);
    }
}

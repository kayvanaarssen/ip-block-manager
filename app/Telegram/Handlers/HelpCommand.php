<?php

namespace App\Telegram\Handlers;

use SergiX44\Nutgram\Nutgram;

class HelpCommand
{
    public function __invoke(Nutgram $bot): void
    {
        $bot->sendMessage(
            "Available commands:\n\n".
            "/block <ip> [reason] - Block IP on all servers\n".
            "/unblock <ip> - Unblock IP from all servers\n".
            "/status <ip> - Check block status of an IP\n".
            "/servers - List all active servers\n".
            "/link <token> - Link your Telegram account\n".
            "/help - Show this help message"
        );
    }
}

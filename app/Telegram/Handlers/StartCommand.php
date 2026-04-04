<?php

namespace App\Telegram\Handlers;

use SergiX44\Nutgram\Nutgram;

class StartCommand
{
    public function __invoke(Nutgram $bot): void
    {
        $bot->sendMessage(
            "Welcome to IP Block Manager!\n\n".
            "Use /link <token> to connect your account.\n".
            "Use /help to see available commands."
        );
    }
}

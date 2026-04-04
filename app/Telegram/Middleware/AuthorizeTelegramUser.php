<?php

namespace App\Telegram\Middleware;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use SergiX44\Nutgram\Nutgram;

class AuthorizeTelegramUser
{
    public function __invoke(Nutgram $bot, $next): void
    {
        $text = $bot->message()?->text ?? '';

        // Allow public commands without auth
        if (preg_match('/^\/(start|help|link)\b/', $text)) {
            $next($bot);
            return;
        }

        $user = User::where('telegram_user_id', $bot->userId())->first();

        if (! $user) {
            $bot->sendMessage(
                "You are not authorized.\n\n".
                "To link your account:\n".
                "1. Log into the web dashboard\n".
                "2. Go to Profile and generate a Telegram link token\n".
                "3. Send /link <token> here"
            );
            return;
        }

        // Set the authenticated user for this request so Auth::id() works
        // throughout the existing services (IpBlockService, AuditService)
        Auth::onceUsingId($user->id);
        $bot->set('user', $user);

        $next($bot);
    }
}

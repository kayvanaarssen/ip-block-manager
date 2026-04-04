<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use SergiX44\Nutgram\Nutgram;

class LinkCommand
{
    public function __invoke(Nutgram $bot, string $token): void
    {
        $token = trim($token);

        if (empty($token)) {
            $bot->sendMessage("Usage: /link <token>\n\nGenerate a token from your Profile page in the web dashboard.");
            return;
        }

        $userId = Cache::get("telegram_link:{$token}");

        if (! $userId) {
            $bot->sendMessage("Invalid or expired token. Please generate a new one from your Profile page.");
            return;
        }

        $user = User::find($userId);

        if (! $user) {
            $bot->sendMessage("User account not found.");
            return;
        }

        // Check if this Telegram account is already linked to another user
        $existingUser = User::where('telegram_user_id', $bot->userId())->first();
        if ($existingUser && $existingUser->id !== $user->id) {
            $bot->sendMessage("This Telegram account is already linked to another user ({$existingUser->name}).");
            return;
        }

        $user->update(['telegram_user_id' => $bot->userId()]);
        Cache::forget("telegram_link:{$token}");

        $bot->sendMessage("Account linked successfully! Welcome, {$user->name}.\n\nUse /help to see available commands.");
    }
}

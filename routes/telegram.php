<?php
/** @var SergiX44\Nutgram\Nutgram $bot */

use App\Telegram\Handlers\BlockCommand;
use App\Telegram\Handlers\HelpCommand;
use App\Telegram\Handlers\LinkCommand;
use App\Telegram\Handlers\ServersCommand;
use App\Telegram\Handlers\StartCommand;
use App\Telegram\Handlers\StatusCommand;
use App\Telegram\Handlers\UnblockCommand;
use App\Telegram\Middleware\AuthorizeTelegramUser;

/*
|--------------------------------------------------------------------------
| Nutgram Handlers
|--------------------------------------------------------------------------
*/

// Global auth middleware
$bot->middleware(AuthorizeTelegramUser::class);

// Public commands (auth check is skipped in middleware)
$bot->onCommand('start', StartCommand::class)
    ->description('Get started with the bot');

$bot->onCommand('help', HelpCommand::class)
    ->description('Show available commands');

$bot->onCommand('link {token}', LinkCommand::class)
    ->description('Link your Telegram account');

// Protected commands
$bot->onCommand('block {ip}', BlockCommand::class)
    ->description('Block an IP on all servers');

$bot->onCommand('unblock {ip}', UnblockCommand::class)
    ->description('Unblock an IP from all servers');

$bot->onCommand('status {ip}', StatusCommand::class)
    ->description('Check block status of an IP');

$bot->onCommand('servers', ServersCommand::class)
    ->description('List all active servers');

<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Facades;

use Illuminate\Support\Facades\Facade;
use Siberfx\TelegramBot\BotManager;
use Siberfx\TelegramBot\TelegramBot;

/**
 * @method static \Siberfx\TelegramBot\TelegramBot bot(?string $name = null)
 * @method static \Siberfx\TelegramBot\Testing\BotTester fake(?string $name = null, ?\Closure $setup = null)
 * @method static array<string> names()
 * @method static \Siberfx\TelegramBot\Matching\Command hears(string|array $patterns, \Closure|string|array $handler)
 * @method static \Siberfx\TelegramBot\Matching\Command receivesImages(\Closure|string|array $handler)
 * @method static \Siberfx\TelegramBot\Matching\Command receivesLocation(\Closure|string|array $handler)
 * @method static \Siberfx\TelegramBot\TelegramBot fallback(\Closure|string|array $handler)
 * @method static \Siberfx\TelegramBot\TelegramBot on(string|array $events, \Closure|string|array $handler)
 * @method static \Siberfx\TelegramBot\TelegramBot group(array $attributes, \Closure $routes)
 * @method static void handle(\Siberfx\TelegramBot\Incoming\Update|array $update)
 * @method static mixed say(string|\Siberfx\TelegramBot\Outgoing\OutgoingMessage $message, int|string|array $recipients, array $params = [])
 * @method static mixed sendRequest(string $method, array $params = [])
 * @method static \Siberfx\TelegramBot\Api\Api api()
 * @method static void startConversation(\Siberfx\TelegramBot\Conversations\Conversation $conversation, int|string|null $chatId = null, int|string|null $userId = null)
 * @method static \Siberfx\TelegramBot\Storage\Storage userStorage(int|string|null $userId = null)
 * @method static \Siberfx\TelegramBot\Storage\Storage chatStorage(int|string|null $chatId = null)
 * @method static \Siberfx\TelegramBot\Storage\Storage botStorage()
 * @method static array me()
 * @method static bool setWebhook(string $url, array $options = [])
 * @method static bool deleteWebhook(bool $dropPendingUpdates = false)
 * @method static bool setCommands(?array $commands = null, array $options = [])
 *
 * @see BotManager
 * @see TelegramBot
 */
final class Telegram extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BotManager::class;
    }
}

<?php

declare(strict_types=1);

use Siberfx\TelegramBot\BotManager;
use Siberfx\TelegramBot\TelegramBot;

if (! function_exists('telegram')) {
    /**
     * Get a Telegram bot instance (the default bot when no name is given).
     */
    function telegram(?string $bot = null): TelegramBot
    {
        return app(BotManager::class)->bot($bot);
    }
}

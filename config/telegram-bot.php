<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default bot
    |--------------------------------------------------------------------------
    |
    | The bot used by the Telegram facade, the telegram() helper and when a
    | bot name is omitted from artisan commands.
    |
    */

    'default' => env('TELEGRAM_BOT', 'main'),

    /*
    |--------------------------------------------------------------------------
    | Bots
    |--------------------------------------------------------------------------
    |
    | Every bot has its own token, routes file, webhook and command list.
    | The webhook for a bot is served at the "webhook.path" below with {bot}
    | replaced by the bot's key (e.g. /telegram/main/webhook).
    |
    */

    'bots' => [

        'main' => [
            'token' => env('TELEGRAM_BOT_TOKEN'),
            'username' => env('TELEGRAM_BOT_USERNAME'),

            // File where you register hears(), fallback(), on() ... for this bot.
            'routes' => base_path('routes/telegram.php'),

            // Leave null to use the package route (route('telegram-bot.webhook')).
            'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),

            // Sent to Telegram on setWebhook and verified on every webhook call.
            'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),

            // Empty array = Telegram default (all update types except a few).
            'allowed_updates' => [],

            // Synced with `php artisan telegram-bot:commands`.
            'commands' => [
                'start' => 'Start the bot',
                'help' => 'Show what I can do',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Bot API
    |--------------------------------------------------------------------------
    |
    | Point "api_url" to your own Bot API server if you run one.
    |
    */

    'api_url' => env('TELEGRAM_API_URL', 'https://api.telegram.org'),

    'http' => [
        'timeout' => (int) env('TELEGRAM_HTTP_TIMEOUT', 30),
        'retries' => (int) env('TELEGRAM_HTTP_RETRIES', 2),
    ],

    // Default parse mode for outgoing text: "HTML", "MarkdownV2", "Markdown" or null.
    'parse_mode' => env('TELEGRAM_PARSE_MODE'),

    /*
    |--------------------------------------------------------------------------
    | Webhook route
    |--------------------------------------------------------------------------
    */

    'webhook' => [
        'enabled' => true,
        'path' => 'telegram/{bot}/webhook',
        'middleware' => [],

        // Report handler exceptions but still answer 200, so Telegram does
        // not retry the same update forever.
        'swallow_exceptions' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | When enabled, the webhook answers immediately and the update is handled
    | by a queued job (Siberfx\TelegramBot\Jobs\ProcessUpdate).
    |
    */

    'queue' => [
        'enabled' => (bool) env('TELEGRAM_QUEUE', false),
        'connection' => env('TELEGRAM_QUEUE_CONNECTION'),
        'queue' => env('TELEGRAM_QUEUE_NAME'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache (conversations & storage)
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'store' => env('TELEGRAM_CACHE_STORE'),
        'prefix' => 'telegram-bot',
    ],

    'conversations' => [
        // Seconds a conversation waits for an answer.
        'ttl' => 1800,

        // Remove the inline keyboard of a question once a button was pressed.
        'remove_keyboard_on_answer' => true,
    ],

    'callbacks' => [
        // Automatically answerCallbackQuery when your handler did not.
        'auto_answer' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Global middleware
    |--------------------------------------------------------------------------
    |
    | Class names implementing any of the Siberfx\TelegramBot\Middleware
    | interfaces (Received, Captured, Matching, Heard, Sending).
    |
    */

    'middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Tinker
    |--------------------------------------------------------------------------
    |
    | The fake user and chat used by `php artisan telegram-bot:tinker`.
    |
    */

    'tinker' => [
        'user' => [
            'id' => 100000001,
            'first_name' => 'Tinker',
            'last_name' => null,
            'username' => 'tinker',
            'language_code' => 'en',
        ],
        'chat_id' => 100000001,
    ],

];

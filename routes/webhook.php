<?php

use Illuminate\Support\Facades\Route;
use Siberfx\TelegramBot\Http\WebhookController;

Route::post(config('telegram-bot.webhook.path', 'telegram/{bot}/webhook'), WebhookController::class)
    ->middleware(config('telegram-bot.webhook.middleware', []))
    ->name('telegram-bot.webhook');

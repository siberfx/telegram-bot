<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot;

use Illuminate\Support\ServiceProvider;
use Siberfx\TelegramBot\Console\CommandsCommand;
use Siberfx\TelegramBot\Console\InstallCommand;
use Siberfx\TelegramBot\Console\MakeConversationCommand;
use Siberfx\TelegramBot\Console\PollCommand;
use Siberfx\TelegramBot\Console\TinkerCommand;
use Siberfx\TelegramBot\Console\WebhookCommand;

final class TelegramBotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/telegram-bot.php', 'telegram-bot');

        // Scoped: a fresh manager per request / queued job (Octane & queue safe).
        $this->app->scoped(BotManager::class, fn ($app): BotManager => new BotManager($app));
        $this->app->alias(BotManager::class, 'telegram-bot');

        $this->app->bind(TelegramBot::class, fn ($app): TelegramBot => $app->make(BotManager::class)->bot());
    }

    public function boot(): void
    {
        if ($this->app['config']->get('telegram-bot.webhook.enabled', true) && ! $this->app->routesAreCached()) {
            $this->loadRoutesFrom(__DIR__.'/../routes/webhook.php');
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/telegram-bot.php' => config_path('telegram-bot.php'),
        ], 'telegram-bot-config');

        $this->publishes([
            __DIR__.'/../stubs/routes.stub' => base_path('routes/telegram.php'),
        ], 'telegram-bot-routes');

        $this->commands([
            InstallCommand::class,
            TinkerCommand::class,
            WebhookCommand::class,
            PollCommand::class,
            CommandsCommand::class,
            MakeConversationCommand::class,
        ]);
    }
}

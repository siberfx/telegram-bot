<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Siberfx\TelegramBot\TelegramBotServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [TelegramBotServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('telegram-bot.bots.main.token', '123456:TEST-TOKEN');
        $app['config']->set('telegram-bot.bots.main.routes', __DIR__.'/Fixtures/routes.php');
        $app['config']->set('telegram-bot.bots.main.webhook_secret', 'top-secret');
    }
}

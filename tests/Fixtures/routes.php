<?php

declare(strict_types=1);

use Siberfx\TelegramBot\TelegramBot;
use Siberfx\TelegramBot\Tests\Fixtures\OnboardingConversation;

/** @var TelegramBot $bot */
$bot->hears('/start', fn (TelegramBot $bot) => $bot->reply('Welcome!'));
$bot->hears('call me {name}', fn (TelegramBot $bot, string $name) => $bot->reply("Hello {$name}"));
$bot->hears('onboard', OnboardingConversation::class);
$bot->hears('stop', fn (TelegramBot $bot) => $bot->reply('Stopped.'))->stopsConversation();
$bot->hears('/ping', fn (TelegramBot $bot) => $bot->reply('pong'))->skipsConversation();
$bot->fallback(fn (TelegramBot $bot) => $bot->reply('Fallback'));

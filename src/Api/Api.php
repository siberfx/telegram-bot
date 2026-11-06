<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Api;

use Siberfx\TelegramBot\TelegramBot;

/**
 * Dynamic access to every Bot API method, routed through "sending" middleware:
 *
 *     $bot->api()->sendPoll(['chat_id' => $id, 'question' => '?', 'options' => [...]]);
 *     $bot->api()->call('setChatTitle', [...]);
 */
final readonly class Api
{
    public function __construct(private TelegramBot $bot) {}

    /** @param array<string, mixed> $params */
    public function call(string $method, array $params = []): mixed
    {
        return $this->bot->sendRequest($method, $params);
    }

    /** @param array{0?: array<string, mixed>} $arguments */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->call($method, $arguments[0] ?? []);
    }
}

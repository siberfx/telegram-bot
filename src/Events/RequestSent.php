<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Events;

use Siberfx\TelegramBot\TelegramBot;

final readonly class RequestSent
{
    /** @param array<string, mixed> $params */
    public function __construct(
        public TelegramBot $bot,
        public string $method,
        public array $params,
        public mixed $result,
    ) {}
}

<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Events;

use Siberfx\TelegramBot\Incoming\Update;
use Siberfx\TelegramBot\TelegramBot;

final readonly class UpdateReceived
{
    public function __construct(
        public TelegramBot $bot,
        public Update $update,
    ) {}
}

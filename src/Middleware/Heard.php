<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Middleware;

use Closure;
use Siberfx\TelegramBot\Incoming\IncomingMessage;
use Siberfx\TelegramBot\TelegramBot;

/**
 * Runs right before the handler of a matched command is called.
 */
interface Heard
{
    /** @param Closure(IncomingMessage): mixed $next */
    public function heard(IncomingMessage $message, Closure $next, TelegramBot $bot): mixed;
}

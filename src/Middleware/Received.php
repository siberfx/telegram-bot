<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Middleware;

use Closure;
use Siberfx\TelegramBot\Incoming\IncomingMessage;
use Siberfx\TelegramBot\TelegramBot;

/**
 * Runs for every incoming message, before anything else. Return $next($message) to continue, anything else swallows the update.
 */
interface Received
{
    /** @param Closure(IncomingMessage): mixed $next */
    public function received(IncomingMessage $message, Closure $next, TelegramBot $bot): mixed;
}

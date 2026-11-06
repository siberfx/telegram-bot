<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Middleware;

use Closure;
use Siberfx\TelegramBot\Incoming\IncomingMessage;
use Siberfx\TelegramBot\TelegramBot;

/**
 * Runs when a message is captured as the answer to a conversation question.
 */
interface Captured
{
    /** @param Closure(IncomingMessage): mixed $next */
    public function captured(IncomingMessage $message, Closure $next, TelegramBot $bot): mixed;
}

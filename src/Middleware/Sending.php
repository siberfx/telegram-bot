<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Middleware;

use Closure;
use Siberfx\TelegramBot\TelegramBot;

/**
 * Runs for every Bot API request made through the bot (reply, say, api()...).
 * $request is ['method' => string, 'params' => array]; return $next($request).
 */
interface Sending
{
    /**
     * @param  array{method: string, params: array<string, mixed>}  $request
     * @param  Closure(array{method: string, params: array<string, mixed>}): mixed  $next
     */
    public function sending(array $request, Closure $next, TelegramBot $bot): mixed;
}

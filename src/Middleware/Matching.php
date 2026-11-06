<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Middleware;

use Siberfx\TelegramBot\Incoming\IncomingMessage;

/**
 * Decides whether a hears() pattern matches. When matching middleware is
 * present, ALL of them must return true; $regexMatched tells whether the
 * pattern itself matched, so middleware can either veto or replace regex
 * matching (e.g. an NLP intent named like the pattern).
 */
interface Matching
{
    public function matching(IncomingMessage $message, string $pattern, bool $regexMatched): bool;
}

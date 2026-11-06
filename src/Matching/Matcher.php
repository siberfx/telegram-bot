<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Matching;

use Siberfx\TelegramBot\Incoming\IncomingMessage;
use Siberfx\TelegramBot\Middleware\Matching;
use Siberfx\TelegramBot\Middleware\MiddlewareStack;

final readonly class Matcher
{
    public function __construct(private MiddlewareStack $middleware) {}

    /**
     * @param  list<Command>  $commands
     * @return list<MatchedCommand>
     */
    public function match(IncomingMessage $message, array $commands): array
    {
        $global = $this->middleware->get('matching');
        $matched = [];

        foreach ($commands as $command) {
            if (! $command->accepts($message)) {
                continue;
            }

            if ($command->isAttachmentListener()) {
                $matched[] = new MatchedCommand($command, []);

                continue;
            }

            /** @var list<Matching> $middleware */
            $middleware = [...$global, ...$this->middleware->filter($command->getMiddleware(), 'matching')];

            foreach ($command->patterns as $pattern) {
                $params = $command->matchPattern($pattern, $message->text);
                $regexMatched = $params !== null;

                $isMatch = $middleware === []
                    ? $regexMatched
                    : array_all($middleware, static fn (Matching $m): bool => $m->matching($message, $pattern, $regexMatched));

                if ($isMatch) {
                    $matched[] = new MatchedCommand($command, $params ?? [], $pattern);

                    break;
                }
            }
        }

        return $matched;
    }
}

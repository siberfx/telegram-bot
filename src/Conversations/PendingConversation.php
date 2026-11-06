<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Conversations;

use Closure;
use Laravel\SerializableClosure\SerializableClosure;
use Siberfx\TelegramBot\Incoming\Answer;
use Siberfx\TelegramBot\Matching\Command;
use Siberfx\TelegramBot\Outgoing\OutgoingMessage;

/**
 * @internal A conversation waiting for an answer, restored from the cache.
 */
final readonly class PendingConversation
{
    /** @param array<string, mixed> $params */
    public function __construct(
        public Conversation $conversation,
        public string|OutgoingMessage $question,
        public mixed $next,
        public array $params,
    ) {}

    /**
     * The callback to run for the given answer: a Closure, a method name on
     * the conversation, or null when none of the patterns matched.
     */
    public function resolveNext(Answer $answer): Closure|string|null
    {
        if (! is_array($this->next)) {
            return $this->unwrap($this->next);
        }

        foreach ($this->next as $entry) {
            $pattern = Command::compile($entry['pattern']);

            if (preg_match($pattern, $answer->value()) === 1 || preg_match($pattern, $answer->text()) === 1) {
                return $this->unwrap($entry['callback']);
            }
        }

        return null;
    }

    private function unwrap(mixed $callback): Closure|string|null
    {
        return match (true) {
            $callback instanceof SerializableClosure => $callback->getClosure(),
            $callback instanceof Closure, is_string($callback) => $callback,
            default => null,
        };
    }
}

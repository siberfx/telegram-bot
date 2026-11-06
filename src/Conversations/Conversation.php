<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Conversations;

use Closure;
use LogicException;
use Siberfx\TelegramBot\Enums\ChatAction;
use Siberfx\TelegramBot\Incoming\IncomingMessage;
use Siberfx\TelegramBot\Outgoing\OutgoingMessage;
use Siberfx\TelegramBot\TelegramBot;

/**
 * A multi-step dialog. Conversations are serialized into the cache between
 * messages, so keep their properties serializable (scalars, arrays, models...).
 *
 *     public function run(): void
 *     {
 *         $this->ask('What is your name?', function (Answer $answer) {
 *             $this->name = $answer->text();
 *             $this->say("Nice to meet you {$this->name}!");
 *         });
 *     }
 *
 * The "next" argument of ask() can be:
 *  - a Closure receiving (Answer $answer, Conversation $conversation),
 *  - the name of a method on the conversation, e.g. 'askEmail' (no closure serialization at all),
 *  - a list of ['pattern' => 'yes|yep', 'callback' => Closure|'method'] entries; when no
 *    pattern matches the answer, the question is repeated.
 */
abstract class Conversation
{
    protected ?TelegramBot $bot = null;

    private string|OutgoingMessage|null $lastQuestion = null;

    private mixed $lastNext = null;

    /** @var array<string, mixed> */
    private array $lastParams = [];

    abstract public function run(): void;

    /**
     * @param  Closure|string|array<int, array{pattern: string, callback: Closure|string}>  $next
     * @param  array<string, mixed>  $params  Extra Bot API parameters for the question
     */
    public function ask(string|OutgoingMessage $question, Closure|string|array $next, array $params = []): static
    {
        $this->bot()->reply($question, $params);
        $this->bot()->storeConversation($this, $question, $next, $params);

        return $this;
    }

    /** Ask the last question again (optionally with a different text). */
    public function repeat(string|OutgoingMessage|null $question = null): static
    {
        if ($this->lastNext === null) {
            throw new LogicException('There is no question to repeat.');
        }

        return $this->ask($question ?? $this->lastQuestion ?? '', $this->lastNext, $this->lastParams);
    }

    /** @param array<string, mixed> $params */
    public function say(string|OutgoingMessage $message, array $params = []): static
    {
        $this->bot()->reply($message, $params);

        return $this;
    }

    public function types(ChatAction $action = ChatAction::Typing): static
    {
        $this->bot()->types($action);

        return $this;
    }

    public function typesAndWaits(float $seconds): static
    {
        $this->bot()->typesAndWaits($seconds);

        return $this;
    }

    /** Return true to end the conversation for this message. */
    public function stopsConversation(IncomingMessage $message): bool
    {
        return false;
    }

    /** Return true to ignore this message (the conversation keeps waiting). */
    public function skipsConversation(IncomingMessage $message): bool
    {
        return false;
    }

    public function bot(): TelegramBot
    {
        return $this->bot ?? throw new LogicException('The conversation has not been started by a bot.');
    }

    /** @internal */
    public function setBot(TelegramBot $bot): static
    {
        $this->bot = $bot;

        return $this;
    }

    /**
     * @internal
     *
     * @param  array<string, mixed>  $params
     */
    public function rememberQuestion(string|OutgoingMessage $question, mixed $next, array $params): static
    {
        $this->lastQuestion = $question;
        $this->lastNext = $next;
        $this->lastParams = $params;

        return $this;
    }

    /**
     * Run a callback while the runtime-only state is detached, so the bot
     * instance never ends up in the cache.
     *
     * @internal
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function detached(Closure $callback): mixed
    {
        $state = [$this->bot, $this->lastQuestion, $this->lastNext, $this->lastParams];
        $this->bot = $this->lastQuestion = $this->lastNext = null;
        $this->lastParams = [];

        try {
            return $callback();
        } finally {
            [$this->bot, $this->lastQuestion, $this->lastNext, $this->lastParams] = $state;
        }
    }
}

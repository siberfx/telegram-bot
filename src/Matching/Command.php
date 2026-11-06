<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Matching;

use Closure;
use Siberfx\TelegramBot\Attachments\AttachmentType;
use Siberfx\TelegramBot\Enums\ChatType;
use Siberfx\TelegramBot\Incoming\IncomingMessage;

/**
 * A registered listener: a text pattern ("call me {name}") or an attachment type.
 */
final class Command
{
    /** @var list<object|string> */
    private array $middleware = [];

    /** @var list<ChatType> */
    private array $chatTypes = [];

    /** @var list<int|string> */
    private array $chatIds = [];

    /** @var list<int|string> */
    private array $userIds = [];

    private bool $stopsConversation = false;

    private bool $skipsConversation = false;

    /**
     * @param  list<string>  $patterns
     * @param  bool  $anyAttachment  Match any attachment when $type is null
     */
    public function __construct(
        public readonly Closure|string|array $handler,
        public readonly array $patterns = [],
        public readonly ?AttachmentType $type = null,
        public readonly bool $anyAttachment = false,
    ) {}

    /** Attach route middleware (Matching and/or Heard). */
    public function middleware(object|string ...$middleware): self
    {
        array_push($this->middleware, ...$middleware);

        return $this;
    }

    /** Only listen in the given chat types. */
    public function in(ChatType|string ...$types): self
    {
        foreach ($types as $type) {
            $this->chatTypes[] = $type instanceof ChatType ? $type : ChatType::from($type);
        }

        return $this;
    }

    public function privateOnly(): self
    {
        return $this->in(ChatType::Private);
    }

    public function groupsOnly(): self
    {
        return $this->in(ChatType::Group, ChatType::Supergroup);
    }

    /** Only listen in the given chats. */
    public function forChats(int|string ...$chatIds): self
    {
        array_push($this->chatIds, ...$chatIds);

        return $this;
    }

    /** Only listen to the given users. */
    public function fromUsers(int|string ...$userIds): self
    {
        array_push($this->userIds, ...$userIds);

        return $this;
    }

    /** Matching this command ends a running conversation. */
    public function stopsConversation(bool $stops = true): self
    {
        $this->stopsConversation = $stops;

        return $this;
    }

    /** Matching this command runs it without interrupting a running conversation. */
    public function skipsConversation(bool $skips = true): self
    {
        $this->skipsConversation = $skips;

        return $this;
    }

    public function isStoppingConversation(): bool
    {
        return $this->stopsConversation;
    }

    public function isSkippingConversation(): bool
    {
        return $this->skipsConversation;
    }

    /** @return list<object|string> */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function isAttachmentListener(): bool
    {
        return $this->type !== null || $this->anyAttachment;
    }

    /** Chat / user / attachment filters. */
    public function accepts(IncomingMessage $message): bool
    {
        if ($this->chatTypes !== [] && ! in_array($message->chat?->type, $this->chatTypes, true)) {
            return false;
        }

        if ($this->chatIds !== [] && ! $this->inList($message->chatId, $this->chatIds)) {
            return false;
        }

        if ($this->userIds !== [] && ! $this->inList($message->senderId, $this->userIds)) {
            return false;
        }

        if ($this->isAttachmentListener()) {
            return $this->anyAttachment ? $message->hasAttachment() : $message->hasAttachment($this->type);
        }

        return true;
    }

    /**
     * @return array<string, string|null>|null Named parameters, or null when not matching.
     */
    public function matchPattern(string $pattern, string $text): ?array
    {
        if (preg_match(self::compile($pattern), self::normalize($text), $matches) !== 1) {
            return null;
        }

        $params = [];

        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value === '' ? null : $value;
            }
        }

        return $params;
    }

    /**
     * "call me {name}"   -> required parameter
     * "help {topic?}"    -> optional parameter (the leading space is optional too)
     * Everything else is a regular expression fragment, matched case-insensitively
     * against the whole message.
     */
    public static function compile(string $pattern): string
    {
        $regex = preg_replace_callback(
            '/( ?)\{(\w+)(\?)?\}/',
            static fn (array $m): string => ($m[3] ?? '') === '?'
                ? "(?:{$m[1]}(?<{$m[2]}>.+?))?"
                : "{$m[1]}(?<{$m[2]}>.+?)",
            $pattern,
        );

        return '~^'.str_replace('~', '\~', (string) $regex).'$~isu';
    }

    /** "/start@MyBot payload" -> "/start payload" */
    public static function normalize(string $text): string
    {
        return trim((string) preg_replace('~^(/[\w-]+)@\w+~u', '$1', $text));
    }

    /** @param list<int|string> $list */
    private function inList(int|string|null $value, array $list): bool
    {
        return $value !== null && in_array((string) $value, array_map('strval', $list), true);
    }
}

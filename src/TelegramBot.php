<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot;

use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use LogicException;
use ReflectionFunction;
use Siberfx\TelegramBot\Api\Api;
use Siberfx\TelegramBot\Attachments\AttachmentType;
use Siberfx\TelegramBot\Attachments\FileAttachment;
use Siberfx\TelegramBot\Contracts\Client;
use Siberfx\TelegramBot\Conversations\Conversation;
use Siberfx\TelegramBot\Conversations\ConversationStore;
use Siberfx\TelegramBot\Conversations\PendingConversation;
use Siberfx\TelegramBot\Enums\ChatAction;
use Siberfx\TelegramBot\Enums\ParseMode;
use Siberfx\TelegramBot\Events\RequestSent;
use Siberfx\TelegramBot\Events\UpdateReceived;
use Siberfx\TelegramBot\Exceptions\TelegramException;
use Siberfx\TelegramBot\Incoming\Answer;
use Siberfx\TelegramBot\Incoming\IncomingMessage;
use Siberfx\TelegramBot\Incoming\Update;
use Siberfx\TelegramBot\Incoming\User;
use Siberfx\TelegramBot\Matching\Command;
use Siberfx\TelegramBot\Matching\MatchedCommand;
use Siberfx\TelegramBot\Matching\Matcher;
use Siberfx\TelegramBot\Middleware\MiddlewareStack;
use Siberfx\TelegramBot\Outgoing\OutgoingMessage;
use Siberfx\TelegramBot\Storage\Storage;
use Throwable;

class TelegramBot
{
    use Macroable;

    /** Update types whose messages are matched against hears()/receives*(). */
    protected const array PROCESSABLE_TYPES = ['message', 'callback_query', 'channel_post', 'business_message'];

    /** @var list<Command> */
    protected array $commands = [];

    protected Closure|string|array|null $fallback = null;

    /** @var array<string, list<Closure|string|array>> */
    protected array $listeners = [];

    /** @var array<class-string<Throwable>, Closure|string|array> */
    protected array $exceptionHandlers = [];

    /** @var list<array<string, mixed>> */
    protected array $groupStack = [];

    protected ?Update $update = null;

    protected ?IncomingMessage $message = null;

    /** @var array<string, string|null> */
    protected array $matches = [];

    protected bool $routesLoaded = false;

    protected bool $callbackAnswered = false;

    protected readonly MiddlewareStack $middleware;

    protected readonly ConversationStore $conversations;

    protected readonly Matcher $matcher;

    /** @param array<string, mixed> $config */
    public function __construct(
        public readonly string $name,
        protected readonly Client $client,
        protected readonly Cache $cache,
        protected readonly array $config,
        protected readonly Container $container,
        protected readonly ?Dispatcher $events = null,
    ) {
        $this->middleware = new MiddlewareStack($container);
        $this->middleware->add(...(array) ($config['middleware'] ?? []));
        $this->conversations = new ConversationStore($cache, $this->cachePrefix(), (int) ($config['conversations']['ttl'] ?? 1800));
        $this->matcher = new Matcher($this->middleware);
    }

    /* -----------------------------------------------------------------
     |  Routes
     | ----------------------------------------------------------------- */

    /**
     * Load the bot's routes file once (it receives the bot as $bot).
     */
    public function loadRoutes(): static
    {
        if ($this->routesLoaded) {
            return $this;
        }

        $this->routesLoaded = true;
        $file = $this->config['routes'] ?? null;

        if (is_string($file) && is_file($file)) {
            (static function (TelegramBot $bot) use ($file): void {
                require $file;
            })($this);
        }

        return $this;
    }

    /** Skip loading the routes file (handlers are registered manually). */
    public function withoutRoutes(): static
    {
        $this->routesLoaded = true;

        return $this;
    }

    /* -----------------------------------------------------------------
     |  Listening
     | ----------------------------------------------------------------- */

    /**
     * Listen for text matching one or more patterns:
     *
     *     $bot->hears('hello', fn (TelegramBot $bot) => $bot->reply('Hi!'));
     *     $bot->hears('call me {name}', fn ($bot, $name) => $bot->reply("Hi {$name}"));
     *     $bot->hears('/start {payload?}', StartHandler::class);
     *     $bot->hears('order', OrderConversation::class);
     *
     * @param  string|list<string>  $patterns
     */
    public function hears(string|array $patterns, Closure|string|array $handler): Command
    {
        return $this->addCommand(new Command($handler, patterns: array_values((array) $patterns)));
    }

    public function receives(AttachmentType $type, Closure|string|array $handler): Command
    {
        return $this->addCommand(new Command($handler, type: $type));
    }

    /** Listen for any attachment. */
    public function receivesAttachments(Closure|string|array $handler): Command
    {
        return $this->addCommand(new Command($handler, anyAttachment: true));
    }

    public function receivesImages(Closure|string|array $handler): Command
    {
        return $this->receives(AttachmentType::Image, $handler);
    }

    public function receivesVideos(Closure|string|array $handler): Command
    {
        return $this->receives(AttachmentType::Video, $handler);
    }

    public function receivesAudio(Closure|string|array $handler): Command
    {
        return $this->receives(AttachmentType::Audio, $handler);
    }

    public function receivesVoice(Closure|string|array $handler): Command
    {
        return $this->receives(AttachmentType::Voice, $handler);
    }

    public function receivesFiles(Closure|string|array $handler): Command
    {
        return $this->receives(AttachmentType::Document, $handler);
    }

    public function receivesStickers(Closure|string|array $handler): Command
    {
        return $this->receives(AttachmentType::Sticker, $handler);
    }

    public function receivesLocation(Closure|string|array $handler): Command
    {
        return $this->receives(AttachmentType::Location, $handler);
    }

    public function receivesContact(Closure|string|array $handler): Command
    {
        return $this->receives(AttachmentType::Contact, $handler);
    }

    /** Called when nothing matched. */
    public function fallback(Closure|string|array $handler): static
    {
        $this->fallback = $handler;

        return $this;
    }

    /**
     * Listen for update types ("callback_query", "inline_query", "my_chat_member",
     * "edited_message", "poll_answer" ...) or message fields ("new_chat_members",
     * "left_chat_member", "successful_payment", "web_app_data" ...).
     *
     * Handlers receive (TelegramBot $bot, mixed $data, Update $update).
     *
     * @param  string|list<string>  $events
     */
    public function on(string|array $events, Closure|string|array $handler): static
    {
        foreach ((array) $events as $event) {
            $this->listeners[$event][] = $handler;
        }

        return $this;
    }

    /**
     * Handle exceptions thrown by handlers: fn (Throwable $e, TelegramBot $bot).
     *
     * @param  class-string<Throwable>  $exception
     */
    public function exception(string $exception, Closure|string|array $handler): static
    {
        $this->exceptionHandlers[$exception] = $handler;

        return $this;
    }

    /**
     * Share attributes between commands:
     * ['middleware' => [...], 'chat_type' => 'private', 'chats' => [...], 'users' => [...]]
     *
     * @param  array<string, mixed>  $attributes
     */
    public function group(array $attributes, Closure $routes): static
    {
        $this->groupStack[] = $attributes;

        try {
            $routes($this);
        } finally {
            array_pop($this->groupStack);
        }

        return $this;
    }

    public function middleware(): MiddlewareStack
    {
        return $this->middleware;
    }

    /** @return list<Command> */
    public function commands(): array
    {
        return $this->commands;
    }

    protected function addCommand(Command $command): Command
    {
        foreach ($this->groupStack as $group) {
            $command->middleware(...(array) ($group['middleware'] ?? []));
            $command->in(...(array) ($group['chat_type'] ?? []));
            $command->forChats(...(array) ($group['chats'] ?? []));
            $command->fromUsers(...(array) ($group['users'] ?? []));
        }

        return $this->commands[] = $command;
    }

    /* -----------------------------------------------------------------
     |  Handling
     | ----------------------------------------------------------------- */

    /**
     * Process an incoming update (webhook, long polling, tinker or tests).
     *
     * @param  Update|array<string, mixed>  $update
     */
    public function handle(Update|array $update): void
    {
        $update = $update instanceof Update ? $update : Update::fromArray($update);

        $this->loadRoutes();
        $this->update = $update;
        $this->message = null;
        $this->matches = [];
        $this->callbackAnswered = false;

        $this->events?->dispatch(new UpdateReceived($this, $update));

        try {
            $message = IncomingMessage::fromUpdate($update);

            if ($message !== null) {
                $message = $this->middleware->runReceived($message, $this);

                if (! $message instanceof IncomingMessage) {
                    return;
                }

                $this->message = $message;
            }

            $this->dispatchListeners($update);

            if ($this->message !== null
                && $this->message->hasContent()
                && in_array($update->type(), self::PROCESSABLE_TYPES, true)) {
                $this->process($this->message);
            }
        } catch (Throwable $e) {
            $this->handleException($e);
        } finally {
            $this->autoAnswerCallback();
        }
    }

    protected function process(IncomingMessage $message): void
    {
        $matches = $this->matcher->match($message, $this->commands);
        $pending = $message->chatId !== null ? $this->conversations->get($message->chatId, $message->senderId) : null;

        if ($pending !== null) {
            $conversation = $pending->conversation->setBot($this);

            $stops = $conversation->stopsConversation($message)
                || array_any($matches, static fn (MatchedCommand $m): bool => $m->command->isStoppingConversation());

            if ($stops) {
                $this->conversations->forget($message->chatId, $message->senderId);

                if ($matches === []) {
                    return;
                }
            } else {
                $skipping = array_filter($matches, static fn (MatchedCommand $m): bool => $m->command->isSkippingConversation());

                if ($skipping !== [] || $conversation->skipsConversation($message)) {
                    array_walk($skipping, fn (MatchedCommand $m) => $this->runCommand($m, $message));

                    return;
                }

                $this->conversations->forget($message->chatId, $message->senderId);
                $this->resumeConversation($pending, $message);

                return;
            }
        }

        if ($matches === []) {
            if ($this->fallback !== null) {
                $this->invoke($this->fallback, [$this]);
            }

            return;
        }

        foreach ($matches as $match) {
            $this->runCommand($match, $message);
        }
    }

    protected function runCommand(MatchedCommand $match, IncomingMessage $message): void
    {
        $this->matches = $match->params;

        $this->middleware->runHeard($message, $this, $match->command->getMiddleware(), function (IncomingMessage $message) use ($match): mixed {
            $this->message = $message;

            return $this->invoke($match->command->handler, [$this, ...array_values($match->params)]);
        });
    }

    protected function resumeConversation(PendingConversation $pending, IncomingMessage $message): void
    {
        $message = $this->middleware->runCaptured($message, $this);

        if (! $message instanceof IncomingMessage) {
            return;
        }

        $this->message = $message;
        $answer = Answer::fromMessage($message);
        $conversation = $pending->conversation
            ->setBot($this)
            ->rememberQuestion($pending->question, $pending->next, $pending->params);

        if ($answer->isInteractive() && ($this->config['conversations']['remove_keyboard_on_answer'] ?? true)) {
            $this->removeInlineKeyboard($message);
        }

        $next = $pending->resolveNext($answer);

        match (true) {
            $next === null => $conversation->repeat(),
            is_string($next) => $conversation->{$next}($answer, $conversation),
            default => $this->invoke($this->bindTo($next, $conversation), [$answer, $conversation]),
        };
    }

    /** @param Update $update */
    protected function dispatchListeners(Update $update): void
    {
        if ($this->listeners === []) {
            return;
        }

        $events = [(string) $update->type() => $update->payload()];

        if ($update->callbackQuery() === null) {
            $events += $update->message() ?? [];
        }

        foreach ($events as $event => $data) {
            foreach ($this->listeners[$event] ?? [] as $handler) {
                $this->invoke($handler, [$this, $data, $update]);
            }
        }
    }

    protected function handleException(Throwable $e): void
    {
        foreach ($this->exceptionHandlers as $class => $handler) {
            if ($e instanceof $class) {
                $this->invoke($handler, [$e, $this]);

                return;
            }
        }

        throw $e;
    }

    protected function autoAnswerCallback(): void
    {
        if ($this->callbackAnswered
            || ! ($this->message?->isCallback() ?? false)
            || ! ($this->config['callbacks']['auto_answer'] ?? true)) {
            return;
        }

        try {
            $this->answerCallback();
        } catch (Throwable) {
            // The query may already be expired; nothing useful to do.
        }
    }

    protected function removeInlineKeyboard(IncomingMessage $message): void
    {
        if ($message->chatId === null || $message->messageId === null) {
            return;
        }

        try {
            $this->client->call('editMessageReplyMarkup', [
                'chat_id' => $message->chatId,
                'message_id' => $message->messageId,
            ]);
        } catch (Throwable) {
            // Message too old or keyboard already gone.
        }
    }

    /** @param list<mixed> $arguments */
    protected function invoke(Closure|string|array $handler, array $arguments): mixed
    {
        if (is_string($handler) && is_subclass_of($handler, Conversation::class)) {
            $this->startConversation($this->container->make($handler));

            return null;
        }

        return $this->resolveCallable($handler)(...$arguments);
    }

    protected function resolveCallable(Closure|string|array $handler): callable
    {
        if ($handler instanceof Closure) {
            return $handler;
        }

        if (is_string($handler)) {
            if (str_contains($handler, '@')) {
                [$class, $method] = explode('@', $handler, 2);

                return [$this->container->make($class), $method];
            }

            $instance = $this->container->make($handler);

            return is_callable($instance)
                ? $instance
                : throw new InvalidArgumentException("Handler [{$handler}] is not invokable.");
        }

        if (isset($handler[0], $handler[1]) && is_string($handler[0]) && class_exists($handler[0])) {
            return [$this->container->make($handler[0]), $handler[1]];
        }

        return is_callable($handler) ? $handler : throw new InvalidArgumentException('Invalid Telegram bot handler.');
    }

    /** Re-bind a restored conversation closure to the restored conversation. */
    protected function bindTo(Closure $closure, Conversation $conversation): Closure
    {
        if ((new ReflectionFunction($closure))->getClosureThis() === null) {
            return $closure;
        }

        return Closure::bind($closure, $conversation, $conversation::class) ?? $closure;
    }

    /* -----------------------------------------------------------------
     |  Sending
     | ----------------------------------------------------------------- */

    /**
     * Reply in the chat of the current message (and its forum topic).
     *
     * @param  array<string, mixed>  $params  Extra Bot API parameters
     */
    public function reply(string|OutgoingMessage $message, array $params = []): mixed
    {
        $incoming = $this->message ?? throw new LogicException('There is no incoming message to reply to. Use say() with a chat id instead.');

        if ($incoming->isTopicMessage && $incoming->threadId !== null) {
            $params += ['message_thread_id' => $incoming->threadId];
        }

        return $this->say($message, $this->requireChatId(), $params);
    }

    /**
     * @param  list<string|OutgoingMessage>  $messages
     * @param  array<string, mixed>  $params
     */
    public function randomReply(array $messages, array $params = []): mixed
    {
        return $this->reply(Arr::random($messages), $params);
    }

    /**
     * Send a message to one or more chats (no incoming message required).
     *
     * @param  int|string|list<int|string>  $recipients
     * @param  array<string, mixed>  $params
     */
    public function say(string|OutgoingMessage $message, int|string|array $recipients, array $params = []): mixed
    {
        if (is_array($recipients)) {
            return array_map(fn (int|string $chatId): mixed => $this->say($message, $chatId, $params), $recipients);
        }

        $outgoing = $message instanceof OutgoingMessage ? $message : OutgoingMessage::create($message);
        [$method, $payload] = $outgoing->toRequest($recipients, $this->defaultParseMode());

        return $this->sendRequest($method, array_replace($payload, $params));
    }

    /**
     * Call any Bot API method through the "sending" middleware.
     *
     * @param  array<string, mixed>  $params
     */
    public function sendRequest(string $method, array $params = []): mixed
    {
        $result = $this->middleware->runSending(
            ['method' => $method, 'params' => $params],
            $this,
            fn (array $request): mixed => $this->client->call($request['method'], $request['params']),
        );

        if ($method === 'answerCallbackQuery') {
            $this->callbackAnswered = true;
        }

        $this->events?->dispatch(new RequestSent($this, $method, $params, $result));

        return $result;
    }

    /** Dynamic access to every Bot API method: $bot->api()->sendDice([...]). */
    public function api(): Api
    {
        return new Api($this);
    }

    public function types(ChatAction $action = ChatAction::Typing): static
    {
        $params = ['chat_id' => $this->requireChatId(), 'action' => $action->value];

        if ($this->message?->isTopicMessage && $this->message->threadId !== null) {
            $params['message_thread_id'] = $this->message->threadId;
        }

        $this->sendRequest('sendChatAction', $params);

        return $this;
    }

    public function typesAndWaits(float $seconds, ChatAction $action = ChatAction::Typing): static
    {
        $this->types($action);
        usleep((int) ($seconds * 1_000_000));

        return $this;
    }

    /** Answer the current callback query (shows a toast or alert). */
    public function answerCallback(?string $text = null, bool $alert = false, ?string $url = null, int $cacheTime = 0): static
    {
        $id = $this->message?->callbackQueryId ?? throw new LogicException('The current update is not a callback query.');

        $this->sendRequest('answerCallbackQuery', array_filter([
            'callback_query_id' => $id,
            'text' => $text,
            'show_alert' => $alert ?: null,
            'url' => $url,
            'cache_time' => $cacheTime ?: null,
        ], static fn (mixed $v): bool => $v !== null));

        return $this;
    }

    /**
     * Edit a message (defaults to the message of the current update, e.g. the
     * message whose button was pressed).
     *
     * @param  array<string, mixed>  $params
     */
    public function editMessage(string|OutgoingMessage $message, ?int $messageId = null, int|string|null $chatId = null, array $params = []): mixed
    {
        $outgoing = $message instanceof OutgoingMessage ? $message : OutgoingMessage::create($message);
        $messageId ??= $this->message?->messageId ?? throw new LogicException('No message id given to edit.');

        [$method, $payload] = $outgoing->toEditRequest($chatId ?? $this->requireChatId(), $messageId, $this->defaultParseMode());

        return $this->sendRequest($method, array_replace($payload, $params));
    }

    public function deleteMessage(?int $messageId = null, int|string|null $chatId = null): bool
    {
        return (bool) $this->sendRequest('deleteMessage', [
            'chat_id' => $chatId ?? $this->requireChatId(),
            'message_id' => $messageId ?? $this->message?->messageId ?? throw new LogicException('No message id given to delete.'),
        ]);
    }

    /* -----------------------------------------------------------------
     |  Conversations
     | ----------------------------------------------------------------- */

    /**
     * Start a conversation with the current user, or proactively in $chatId.
     */
    public function startConversation(Conversation $conversation, int|string|null $chatId = null, int|string|null $userId = null): void
    {
        $original = $this->message;

        if ($chatId !== null) {
            $this->message = IncomingMessage::synthetic($chatId, $userId);
        }

        try {
            $conversation->setBot($this)->run();
        } finally {
            $this->message = $original;
        }
    }

    /**
     * @internal Used by Conversation::ask().
     *
     * @param  array<string, mixed>  $params
     */
    public function storeConversation(Conversation $conversation, string|OutgoingMessage $question, mixed $next, array $params = []): void
    {
        $this->conversations->put($this->requireChatId(), $this->message?->senderId, $conversation, $question, $next, $params);
    }

    public function hasConversation(int|string|null $chatId = null, int|string|null $userId = null): bool
    {
        return $this->conversations->has($chatId ?? $this->requireChatId(), $userId ?? $this->message?->senderId);
    }

    public function stopConversation(int|string|null $chatId = null, int|string|null $userId = null): void
    {
        $this->conversations->forget($chatId ?? $this->requireChatId(), $userId ?? $this->message?->senderId);
    }

    /* -----------------------------------------------------------------
     |  Storage
     | ----------------------------------------------------------------- */

    public function userStorage(int|string|null $userId = null): Storage
    {
        $userId ??= $this->message?->senderId ?? throw new LogicException('No user id available for user storage.');

        return new Storage($this->cache, $this->cachePrefix().':storage:user:'.$userId);
    }

    public function chatStorage(int|string|null $chatId = null): Storage
    {
        return new Storage($this->cache, $this->cachePrefix().':storage:chat:'.($chatId ?? $this->requireChatId()));
    }

    public function botStorage(): Storage
    {
        return new Storage($this->cache, $this->cachePrefix().':storage:bot');
    }

    /* -----------------------------------------------------------------
     |  Files
     | ----------------------------------------------------------------- */

    /** Resolve the download URL of an incoming file (calls getFile). */
    public function fileUrl(FileAttachment|string $file): string
    {
        $fileId = $file instanceof FileAttachment
            ? ($file->fileId() ?? throw new TelegramException('The attachment has no Telegram file id.'))
            : $file;

        $info = (array) $this->client->call('getFile', ['file_id' => $fileId]);

        return $this->client->fileUrl((string) ($info['file_path'] ?? throw new TelegramException('Telegram did not return a file path.')));
    }

    /** Download an incoming file and return its contents. */
    public function download(FileAttachment|string $file): string
    {
        return $this->container->make(HttpFactory::class)->get($this->fileUrl($file))->throw()->body();
    }

    /** Download an incoming file to a local path and return the path. */
    public function downloadTo(FileAttachment|string $file, string $path): string
    {
        if (@file_put_contents($path, $this->download($file)) === false) {
            throw new TelegramException("Unable to write file [{$path}].");
        }

        return $path;
    }

    /* -----------------------------------------------------------------
     |  Bot management
     | ----------------------------------------------------------------- */

    /** @return array<string, mixed> */
    public function me(): array
    {
        return (array) $this->client->call('getMe');
    }

    /** @param array<string, mixed> $options */
    public function setWebhook(string $url, array $options = []): bool
    {
        return (bool) $this->client->call('setWebhook', array_filter([
            'url' => $url,
            'secret_token' => $this->config['webhook_secret'] ?? null,
            'allowed_updates' => ($this->config['allowed_updates'] ?? []) ?: null,
        ] + $options, static fn (mixed $v): bool => $v !== null && $v !== ''));
    }

    public function deleteWebhook(bool $dropPendingUpdates = false): bool
    {
        return (bool) $this->client->call('deleteWebhook', ['drop_pending_updates' => $dropPendingUpdates]);
    }

    /** @return array<string, mixed> */
    public function webhookInfo(): array
    {
        return (array) $this->client->call('getWebhookInfo');
    }

    /**
     * @param  array<string, string>|list<array{command: string, description: string}>|null  $commands  Defaults to config
     * @param  array<string, mixed>  $options  scope, language_code
     */
    public function setCommands(?array $commands = null, array $options = []): bool
    {
        $commands ??= (array) ($this->config['commands'] ?? []);

        $list = array_is_list($commands)
            ? $commands
            : array_map(static fn (string $command, string $description): array => [
                'command' => ltrim($command, '/'),
                'description' => $description,
            ], array_keys($commands), $commands);

        return (bool) $this->client->call('setMyCommands', ['commands' => $list] + $options);
    }

    /**
     * @param  list<string>  $allowedUpdates
     * @return list<array<string, mixed>>
     */
    public function getUpdates(int $offset = 0, int $timeout = 30, array $allowedUpdates = []): array
    {
        return (array) $this->client->call('getUpdates', array_filter([
            'offset' => $offset ?: null,
            'timeout' => $timeout,
            'allowed_updates' => ($allowedUpdates ?: ($this->config['allowed_updates'] ?? [])) ?: null,
        ], static fn (mixed $v): bool => $v !== null));
    }

    /* -----------------------------------------------------------------
     |  Context
     | ----------------------------------------------------------------- */

    public function message(): ?IncomingMessage
    {
        return $this->message;
    }

    public function update(): ?Update
    {
        return $this->update;
    }

    public function user(): ?User
    {
        return $this->message?->sender;
    }

    public function chatId(): int|string|null
    {
        return $this->message?->chatId;
    }

    /** @return array<string, string|null> Parameters of the matched pattern. */
    public function matches(): array
    {
        return $this->matches;
    }

    public function client(): Client
    {
        return $this->client;
    }

    public function config(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->config : Arr::get($this->config, $key, $default);
    }

    protected function requireChatId(): int|string
    {
        return $this->message?->chatId ?? throw new LogicException('The current update has no chat.');
    }

    protected function defaultParseMode(): ?ParseMode
    {
        $mode = $this->config['parse_mode'] ?? null;

        return $mode instanceof ParseMode ? $mode : ParseMode::tryFrom((string) $mode);
    }

    protected function cachePrefix(): string
    {
        return ($this->config['cache']['prefix'] ?? 'telegram-bot').':'.$this->name;
    }
}

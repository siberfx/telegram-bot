<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot;

use Closure;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory as HttpFactory;
use InvalidArgumentException;
use Siberfx\TelegramBot\Api\FakeClient;
use Siberfx\TelegramBot\Api\HttpClient;
use Siberfx\TelegramBot\Contracts\Client;
use Siberfx\TelegramBot\Testing\BotTester;

/**
 * Resolves configured bots. Unknown method calls are forwarded to the default bot.
 *
 * @mixin TelegramBot
 */
class BotManager
{
    /** @var array<string, TelegramBot> */
    protected array $bots = [];

    public function __construct(protected readonly Container $app) {}

    public function bot(?string $name = null): TelegramBot
    {
        $name ??= $this->getDefaultBot();

        return $this->bots[$name] ??= $this->make($name);
    }

    /**
     * Build a fresh bot instance (not cached by the manager).
     */
    public function make(string $name, ?Client $client = null, ?Cache $cache = null): TelegramBot
    {
        $config = $this->config($name);

        $client ??= new HttpClient(
            $this->app->make(HttpFactory::class),
            (string) ($config['token'] ?? ''),
            (string) ($config['api_url'] ?? 'https://api.telegram.org'),
            (int) ($config['http']['timeout'] ?? 30),
            (int) ($config['http']['retries'] ?? 2),
        );

        $cache ??= $this->app->make('cache')->store($config['cache']['store'] ?? null);

        return new TelegramBot(
            $name,
            $client,
            $cache,
            $config,
            $this->app,
            $this->app->bound(Dispatcher::class) ? $this->app->make(Dispatcher::class) : null,
        );
    }

    /**
     * Swap a bot for a fake one and get a fluent tester:
     *
     *     Telegram::fake()->receives('hi')->assertReply('Hello!');
     *     Telegram::fake(setup: fn (TelegramBot $bot) => $bot->hears(...));
     */
    public function fake(?string $name = null, ?Closure $setup = null): BotTester
    {
        $name ??= $this->getDefaultBot();
        $client = new FakeClient;
        $bot = $this->make($name, $client, new CacheRepository(new ArrayStore));

        if ($setup !== null) {
            $bot->withoutRoutes();
            $setup($bot);
        }

        $this->bots[$name] = $bot;

        return new BotTester($bot, $client);
    }

    /**
     * The merged configuration (shared options + bot options) of a bot.
     *
     * @return array<string, mixed>
     */
    public function config(string $name): array
    {
        $config = (array) $this->app->make('config')->get('telegram-bot', []);
        $bot = $config['bots'][$name] ?? null;

        if (! is_array($bot)) {
            throw new InvalidArgumentException("Telegram bot [{$name}] is not configured.");
        }

        unset($config['bots'], $config['default']);

        return array_replace($config, $bot);
    }

    public function has(string $name): bool
    {
        return is_array($this->app->make('config')->get("telegram-bot.bots.{$name}"));
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys((array) $this->app->make('config')->get('telegram-bot.bots', []));
    }

    public function getDefaultBot(): string
    {
        return (string) $this->app->make('config')->get('telegram-bot.default', 'main');
    }

    public function forget(?string $name = null): void
    {
        if ($name === null) {
            $this->bots = [];

            return;
        }

        unset($this->bots[$name]);
    }

    /** @param list<mixed> $arguments */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->bot()->{$method}(...$arguments);
    }
}

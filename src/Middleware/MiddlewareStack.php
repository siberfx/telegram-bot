<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Middleware;

use Closure;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Siberfx\TelegramBot\Incoming\IncomingMessage;
use Siberfx\TelegramBot\TelegramBot;

final class MiddlewareStack
{
    private const array HOOKS = [
        Received::class => 'received',
        Captured::class => 'captured',
        Matching::class => 'matching',
        Heard::class => 'heard',
        Sending::class => 'sending',
    ];

    /** @var array<string, list<object|string>> */
    private array $stack = [
        'received' => [],
        'captured' => [],
        'matching' => [],
        'heard' => [],
        'sending' => [],
    ];

    public function __construct(private readonly Container $container) {}

    /**
     * Register middleware for every hook its class implements.
     */
    public function add(object|string ...$middleware): self
    {
        foreach ($middleware as $item) {
            $class = is_string($item) ? $item : $item::class;
            $added = false;

            foreach (self::HOOKS as $interface => $hook) {
                if (is_a($class, $interface, true)) {
                    $this->stack[$hook][] = $item;
                    $added = true;
                }
            }

            if (! $added) {
                throw new InvalidArgumentException("[{$class}] does not implement any Telegram bot middleware interface.");
            }
        }

        return $this;
    }

    public function received(Received|string ...$middleware): self
    {
        return $this->push('received', $middleware);
    }

    public function captured(Captured|string ...$middleware): self
    {
        return $this->push('captured', $middleware);
    }

    public function matching(Matching|string ...$middleware): self
    {
        return $this->push('matching', $middleware);
    }

    public function heard(Heard|string ...$middleware): self
    {
        return $this->push('heard', $middleware);
    }

    public function sending(Sending|string ...$middleware): self
    {
        return $this->push('sending', $middleware);
    }

    /** @return list<object> */
    public function get(string $hook): array
    {
        return array_map($this->resolve(...), $this->stack[$hook] ?? []);
    }

    /**
     * Pick the middleware of a given hook out of an arbitrary list (route middleware).
     *
     * @param  list<object|string>  $middleware
     * @return list<object>
     */
    public function filter(array $middleware, string $hook): array
    {
        $interface = array_search($hook, self::HOOKS, true);

        return array_values(array_filter(
            array_map($this->resolve(...), $middleware),
            static fn (object $m): bool => $m instanceof $interface,
        ));
    }

    public function runReceived(IncomingMessage $message, TelegramBot $bot): mixed
    {
        return $this->pipe($this->get('received'), 'received', $message, $bot, static fn (IncomingMessage $m) => $m);
    }

    public function runCaptured(IncomingMessage $message, TelegramBot $bot): mixed
    {
        return $this->pipe($this->get('captured'), 'captured', $message, $bot, static fn (IncomingMessage $m) => $m);
    }

    /** @param list<object|string> $routeMiddleware */
    public function runHeard(IncomingMessage $message, TelegramBot $bot, array $routeMiddleware, Closure $destination): mixed
    {
        $middleware = [...$this->get('heard'), ...$this->filter($routeMiddleware, 'heard')];

        return $this->pipe($middleware, 'heard', $message, $bot, $destination);
    }

    /** @param array{method: string, params: array<string, mixed>} $request */
    public function runSending(array $request, TelegramBot $bot, Closure $destination): mixed
    {
        return $this->pipe($this->get('sending'), 'sending', $request, $bot, $destination);
    }

    /** @param list<object> $middleware */
    private function pipe(array $middleware, string $method, mixed $passable, TelegramBot $bot, Closure $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($middleware),
            static fn (Closure $next, object $m): Closure => static fn (mixed $p): mixed => $m->{$method}($p, $next, $bot),
            $destination,
        );

        return $pipeline($passable);
    }

    /** @param list<object|string> $middleware */
    private function push(string $hook, array $middleware): self
    {
        array_push($this->stack[$hook], ...$middleware);

        return $this;
    }

    private function resolve(object|string $middleware): object
    {
        return is_string($middleware) ? $this->container->make($middleware) : $middleware;
    }
}

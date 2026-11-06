<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Outgoing;

final class Button
{
    /** @var array<string, mixed> */
    private array $options = [];

    private function __construct(private readonly string $text) {}

    public static function create(string $text): self
    {
        return new self($text);
    }

    public function text(): string
    {
        return $this->text;
    }

    /** Callback data sent back when the (inline) button is pressed. Max 64 bytes. */
    public function value(string $value): self
    {
        return $this->set('callback_data', $value);
    }

    public function url(string $url): self
    {
        return $this->set('url', $url);
    }

    public function webApp(string $url): self
    {
        return $this->set('web_app', ['url' => $url]);
    }

    public function loginUrl(string $url, array $options = []): self
    {
        return $this->set('login_url', ['url' => $url] + $options);
    }

    public function switchInlineQuery(string $query = '', bool $currentChat = false): self
    {
        return $this->set($currentChat ? 'switch_inline_query_current_chat' : 'switch_inline_query', $query);
    }

    public function copyText(string $text): self
    {
        return $this->set('copy_text', ['text' => $text]);
    }

    public function pay(): self
    {
        return $this->set('pay', true);
    }

    /** Reply keyboard only: share the user's phone number. */
    public function requestContact(): self
    {
        return $this->set('request_contact', true);
    }

    /** Reply keyboard only: share the user's location. */
    public function requestLocation(): self
    {
        return $this->set('request_location', true);
    }

    public function set(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toInline(): array
    {
        $button = ['text' => $this->text] + $this->options;

        $hasAction = array_intersect_key($button, array_flip([
            'callback_data', 'url', 'web_app', 'login_url', 'switch_inline_query',
            'switch_inline_query_current_chat', 'copy_text', 'pay',
        ])) !== [];

        if (! $hasAction) {
            $button['callback_data'] = $this->text;
        }

        unset($button['request_contact'], $button['request_location']);

        return $button;
    }

    /** @return array<string, mixed> */
    public function toReply(): array
    {
        return ['text' => $this->text] + array_intersect_key($this->options, array_flip([
            'request_contact', 'request_location', 'web_app',
        ]));
    }
}

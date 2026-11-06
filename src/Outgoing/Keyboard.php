<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Outgoing;

use JsonSerializable;

/**
 *     Keyboard::inline()->row(Button::create('Yes')->value('y'), Button::create('No')->value('n'));
 *     Keyboard::reply()->buttons([...], columns: 2)->resize()->oneTime();
 *     Keyboard::remove();
 */
final class Keyboard implements JsonSerializable
{
    /** @var list<list<Button>> */
    private array $rows = [];

    /** @var array<string, mixed> */
    private array $options = [];

    private function __construct(private readonly bool $inline) {}

    public static function inline(): self
    {
        return new self(true);
    }

    public static function reply(): self
    {
        return new self(false);
    }

    /** @return array<string, mixed> */
    public static function remove(bool $selective = false): array
    {
        return array_filter(['remove_keyboard' => true, 'selective' => $selective ?: null]);
    }

    /** @return array<string, mixed> */
    public static function forceReply(?string $placeholder = null, bool $selective = false): array
    {
        return array_filter([
            'force_reply' => true,
            'input_field_placeholder' => $placeholder,
            'selective' => $selective ?: null,
        ]);
    }

    public function row(Button ...$buttons): self
    {
        $this->rows[] = array_values($buttons);

        return $this;
    }

    /** @param iterable<Button> $buttons */
    public function buttons(iterable $buttons, int $columns = 1): self
    {
        $buttons = is_array($buttons) ? array_values($buttons) : iterator_to_array($buttons, false);

        foreach (array_chunk($buttons, max(1, $columns)) as $row) {
            $this->row(...$row);
        }

        return $this;
    }

    public function isInline(): bool
    {
        return $this->inline;
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    public function resize(bool $resize = true): self
    {
        return $this->option('resize_keyboard', $resize);
    }

    public function oneTime(bool $oneTime = true): self
    {
        return $this->option('one_time_keyboard', $oneTime);
    }

    public function persistent(bool $persistent = true): self
    {
        return $this->option('is_persistent', $persistent);
    }

    public function placeholder(string $placeholder): self
    {
        return $this->option('input_field_placeholder', $placeholder);
    }

    public function selective(bool $selective = true): self
    {
        return $this->option('selective', $selective);
    }

    private function option(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        if ($this->inline) {
            return ['inline_keyboard' => array_map(
                static fn (array $row): array => array_map(static fn (Button $b): array => $b->toInline(), $row),
                $this->rows,
            )];
        }

        return ['keyboard' => array_map(
            static fn (array $row): array => array_map(static fn (Button $b): array => $b->toReply(), $row),
            $this->rows,
        )] + $this->options;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

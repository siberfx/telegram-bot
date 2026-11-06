<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Outgoing;

/**
 * A message with answer buttons, rendered as an inline keyboard:
 *
 *     Question::create('Do you like pizza?')
 *         ->addButtons([Button::create('Yes')->value('yes'), Button::create('No')->value('no')])
 *         ->columns(2);
 */
class Question extends OutgoingMessage
{
    /** @var list<Button> */
    protected array $buttons = [];

    protected int $columns = 1;

    public function addButton(Button $button): static
    {
        $this->buttons[] = $button;

        return $this;
    }

    /** @param iterable<Button> $buttons */
    public function addButtons(iterable $buttons): static
    {
        foreach ($buttons as $button) {
            $this->addButton($button);
        }

        return $this;
    }

    public function columns(int $columns): static
    {
        $this->columns = max(1, $columns);

        return $this;
    }

    /** @return list<Button> */
    public function buttons(): array
    {
        return $this->buttons;
    }

    public function replyMarkup(): ?array
    {
        if ($this->keyboard !== null || $this->buttons === []) {
            return parent::replyMarkup();
        }

        return Keyboard::inline()->buttons($this->buttons, $this->columns)->toArray();
    }
}

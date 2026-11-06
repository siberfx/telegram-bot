<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Tests\Fixtures;

use Siberfx\TelegramBot\Conversations\Conversation;
use Siberfx\TelegramBot\Incoming\Answer;
use Siberfx\TelegramBot\Outgoing\Button;
use Siberfx\TelegramBot\Outgoing\Question;

final class OnboardingConversation extends Conversation
{
    private string $name = '';

    public function run(): void
    {
        $this->ask('What is your name?', function (Answer $answer) {
            $this->name = $answer->text();
            $this->askColor();
        });
    }

    public function askColor(): void
    {
        $question = Question::create("Hi {$this->name}, pick a color")
            ->addButtons([
                Button::create('Red')->value('red'),
                Button::create('Blue')->value('blue'),
            ])
            ->columns(2);

        $this->ask($question, [
            ['pattern' => 'red', 'callback' => 'finish'],
            ['pattern' => 'blue', 'callback' => fn (Answer $answer) => $this->say("{$this->name} likes {$answer->text()}")],
        ]);
    }

    public function finish(Answer $answer): void
    {
        $this->say("{$this->name} likes {$answer->text()} ({$answer->value()})");
    }
}

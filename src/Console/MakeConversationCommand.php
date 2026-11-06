<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:telegram-conversation')]
final class MakeConversationCommand extends GeneratorCommand
{
    protected $name = 'make:telegram-conversation';

    protected $description = 'Create a new Telegram bot conversation class';

    protected $type = 'Conversation';

    protected function getStub(): string
    {
        return __DIR__.'/../../stubs/conversation.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Telegram\\Conversations';
    }
}

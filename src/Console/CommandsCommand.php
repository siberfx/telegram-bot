<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Console;

use Illuminate\Console\Command;
use Siberfx\TelegramBot\BotManager;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'telegram-bot:commands')]
final class CommandsCommand extends Command
{
    protected $signature = 'telegram-bot:commands
        {--bot= : The bot (defaults to the default bot)}
        {--list : Show the commands currently registered at Telegram}
        {--delete : Remove all commands}';

    protected $description = 'Sync the bot command menu (setMyCommands) from config';

    public function handle(BotManager $manager): int
    {
        $name = (string) ($this->option('bot') ?: $manager->getDefaultBot());

        if (! $manager->has($name)) {
            $this->components->error("Telegram bot [{$name}] is not configured.");

            return self::FAILURE;
        }

        $bot = $manager->bot($name);

        if ($this->option('list')) {
            $commands = (array) $bot->api()->getMyCommands();
            $this->table(['Command', 'Description'], array_map(
                static fn (array $c): array => ['/'.$c['command'], $c['description']],
                $commands,
            ));

            return self::SUCCESS;
        }

        if ($this->option('delete')) {
            $bot->api()->deleteMyCommands();
            $this->components->info('Commands deleted.');

            return self::SUCCESS;
        }

        $commands = (array) $bot->config('commands', []);
        $bot->setCommands($commands);

        foreach ($commands as $command => $description) {
            $this->components->twoColumnDetail('/'.ltrim((string) $command, '/'), (string) $description);
        }

        $this->components->info(count($commands).' command(s) synced for ['.$name.'].');

        return self::SUCCESS;
    }
}

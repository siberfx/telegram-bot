<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Console;

use Illuminate\Console\Command;
use Siberfx\TelegramBot\BotManager;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'telegram-bot:webhook')]
final class WebhookCommand extends Command
{
    protected $signature = 'telegram-bot:webhook
        {action=info : set, info or delete}
        {--bot= : The bot (defaults to the default bot)}
        {--url= : Webhook URL (defaults to config or the package route)}
        {--drop-pending : Drop pending updates}';

    protected $description = 'Set, inspect or delete the Telegram webhook of a bot';

    public function handle(BotManager $manager): int
    {
        $name = (string) ($this->option('bot') ?: $manager->getDefaultBot());

        if (! $manager->has($name)) {
            $this->components->error("Telegram bot [{$name}] is not configured.");

            return self::FAILURE;
        }

        return match ($this->argument('action')) {
            'set' => $this->set($name, $manager),
            'delete' => $this->delete($name, $manager),
            'info' => $this->showInfo($manager->bot($name)->webhookInfo()),
            default => $this->invalidAction(),
        };
    }

    private function set(string $name, BotManager $manager): int
    {
        $url = (string) ($this->option('url') ?: ($manager->config($name)['webhook_url'] ?? null) ?: route('telegram-bot.webhook', ['bot' => $name]));

        if (! str_starts_with($url, 'https://')) {
            $this->components->warn('Telegram only delivers webhooks to HTTPS URLs (use a tunnel such as `herd share` locally).');
        }

        $manager->bot($name)->setWebhook($url, ['drop_pending_updates' => $this->option('drop-pending') ?: null]);
        $this->components->info("Webhook for [{$name}] set to {$url}");

        return self::SUCCESS;
    }

    private function delete(string $name, BotManager $manager): int
    {
        $manager->bot($name)->deleteWebhook((bool) $this->option('drop-pending'));
        $this->components->info("Webhook for [{$name}] deleted.");

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $info */
    private function showInfo(array $info): int
    {
        foreach ($info as $key => $value) {
            $this->components->twoColumnDetail(
                (string) $key,
                is_scalar($value) ? (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value) : json_encode($value),
            );
        }

        if (($info['url'] ?? '') === '') {
            $this->components->warn('No webhook is set (long polling mode).');
        }

        return self::SUCCESS;
    }

    private function invalidAction(): int
    {
        $this->components->error('The action must be one of: set, info, delete.');

        return self::INVALID;
    }
}

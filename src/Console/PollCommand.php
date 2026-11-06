<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Console;

use Illuminate\Console\Command;
use Siberfx\TelegramBot\BotManager;
use Siberfx\TelegramBot\Exceptions\TelegramApiException;
use Siberfx\TelegramBot\Incoming\Update;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

/**
 * Receive updates with long polling — ideal for local development, no public URL needed.
 */
#[AsCommand(name: 'telegram-bot:poll')]
final class PollCommand extends Command
{
    protected $signature = 'telegram-bot:poll
        {--bot= : The bot (defaults to the default bot)}
        {--timeout=30 : Long polling timeout in seconds}
        {--once : Fetch a single batch of updates and exit}
        {--delete-webhook : Remove an active webhook first}';

    protected $description = 'Run a Telegram bot with long polling (getUpdates)';

    private bool $shouldStop = false;

    public function handle(BotManager $manager): int
    {
        $name = (string) ($this->option('bot') ?: $manager->getDefaultBot());

        if (! $manager->has($name)) {
            $this->components->error("Telegram bot [{$name}] is not configured.");

            return self::FAILURE;
        }

        $bot = $manager->bot($name);

        if (($bot->webhookInfo()['url'] ?? '') !== '') {
            if (! $this->option('delete-webhook')) {
                $this->components->error('A webhook is active, Telegram does not allow getUpdates. Re-run with --delete-webhook.');

                return self::FAILURE;
            }

            $bot->deleteWebhook();
            $this->components->info('Webhook deleted.');
        }

        if (extension_loaded('pcntl')) {
            $this->trap([SIGINT, SIGTERM], fn () => $this->shouldStop = true);
        }

        $me = $bot->me();
        $this->components->info('Polling @'.($me['username'] ?? $name).' — press Ctrl+C to stop.');

        $offset = 0;
        $timeout = max(0, (int) $this->option('timeout'));

        while (! $this->shouldStop) {
            try {
                $updates = $bot->getUpdates($offset, $timeout);
            } catch (TelegramApiException $e) {
                $this->components->error($e->getMessage());
                sleep($e->retryAfter() ?? 3);

                continue;
            }

            foreach ($updates as $data) {
                $update = Update::fromArray($data);
                $offset = $update->id() + 1;
                $this->describe($update);

                try {
                    $manager->bot($name)->handle($update);
                } catch (Throwable $e) {
                    report($e);
                    $this->components->error(class_basename($e).': '.$e->getMessage());
                }
            }

            if ($this->option('once')) {
                break;
            }
        }

        return self::SUCCESS;
    }

    private function describe(Update $update): void
    {
        $message = $update->message() ?? [];
        $from = $update->get('callback_query.from') ?? $message['from'] ?? [];
        $text = $update->get('callback_query.data') ?? $message['text'] ?? $message['caption'] ?? '';

        $this->line(sprintf(
            '<fg=gray>%s</> <fg=cyan>#%d</> %s <fg=yellow>%s</> %s',
            now()->format('H:i:s'),
            $update->id(),
            $update->type(),
            isset($from['username']) ? '@'.$from['username'] : ($from['first_name'] ?? ''),
            mb_strimwidth((string) $text, 0, 80, '…'),
        ));
    }
}

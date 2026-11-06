<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'telegram-bot:install')]
final class InstallCommand extends Command
{
    protected $signature = 'telegram-bot:install {--force : Overwrite existing files}';

    protected $description = 'Publish the Telegram bot config and routes file';

    public function handle(): int
    {
        $this->callSilently('vendor:publish', [
            '--tag' => 'telegram-bot-config',
            '--force' => (bool) $this->option('force'),
        ]);
        $this->components->task('Config published to config/telegram-bot.php');

        $routes = base_path('routes/telegram.php');

        if (! is_file($routes) || $this->option('force')) {
            copy(__DIR__.'/../../stubs/routes.stub', $routes);
            $this->components->task('Routes created at routes/telegram.php');
        } else {
            $this->components->task('routes/telegram.php already exists', fn () => true);
        }

        $this->newLine();
        $this->components->info('Add these to your .env:');
        $this->line('  TELEGRAM_BOT_TOKEN=123456:ABC...');
        $this->line('  TELEGRAM_BOT_USERNAME=my_bot');
        $this->line('  TELEGRAM_WEBHOOK_SECRET='.bin2hex(random_bytes(16)));
        $this->newLine();
        $this->components->bulletList([
            '<fg=cyan>php artisan telegram-bot:tinker</>   chat with your bot in the console',
            '<fg=cyan>php artisan telegram-bot:poll</>     run it against Telegram with long polling',
            '<fg=cyan>php artisan telegram-bot:webhook set</>  register the webhook in production',
        ]);

        return self::SUCCESS;
    }
}

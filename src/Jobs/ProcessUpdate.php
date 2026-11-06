<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Siberfx\TelegramBot\BotManager;

final class ProcessUpdate implements ShouldQueue
{
    use Queueable;

    /** Updates are not idempotent (replies would be sent twice). */
    public int $tries = 1;

    /** @param array<string, mixed> $update */
    public function __construct(
        public readonly string $bot,
        public readonly array $update,
    ) {}

    public function handle(BotManager $manager): void
    {
        $manager->bot($this->bot)->handle($this->update);
    }
}

<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Matching;

final readonly class MatchedCommand
{
    /** @param array<string, string|null> $params */
    public function __construct(
        public Command $command,
        public array $params,
        public ?string $pattern = null,
    ) {}
}

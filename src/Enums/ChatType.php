<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Enums;

enum ChatType: string
{
    case Private = 'private';
    case Group = 'group';
    case Supergroup = 'supergroup';
    case Channel = 'channel';

    public function isGroup(): bool
    {
        return $this === self::Group || $this === self::Supergroup;
    }
}

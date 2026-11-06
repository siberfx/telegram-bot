<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Enums;

enum ParseMode: string
{
    case Html = 'HTML';
    case MarkdownV2 = 'MarkdownV2';
    case Markdown = 'Markdown';
}

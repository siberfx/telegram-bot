<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Attachments;

final class Sticker extends FileAttachment
{
    public function type(): AttachmentType
    {
        return AttachmentType::Sticker;
    }
}

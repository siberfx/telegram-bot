<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Attachments;

final class Animation extends FileAttachment
{
    public function type(): AttachmentType
    {
        return AttachmentType::Animation;
    }
}

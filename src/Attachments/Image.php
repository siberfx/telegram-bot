<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Attachments;

final class Image extends FileAttachment
{
    public function type(): AttachmentType
    {
        return AttachmentType::Image;
    }
}

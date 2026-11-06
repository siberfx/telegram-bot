<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Attachments;

final class Video extends FileAttachment
{
    public function type(): AttachmentType
    {
        return AttachmentType::Video;
    }
}

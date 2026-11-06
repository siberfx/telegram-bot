<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Attachments;

enum AttachmentType: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Voice = 'voice';
    case Document = 'document';
    case Animation = 'animation';
    case Sticker = 'sticker';
    case Location = 'location';
    case Contact = 'contact';

    /** Bot API method used to send this attachment. */
    public function method(): string
    {
        return match ($this) {
            self::Image => 'sendPhoto',
            self::Video => 'sendVideo',
            self::Audio => 'sendAudio',
            self::Voice => 'sendVoice',
            self::Document => 'sendDocument',
            self::Animation => 'sendAnimation',
            self::Sticker => 'sendSticker',
            self::Location => 'sendLocation',
            self::Contact => 'sendContact',
        };
    }

    /** Bot API field that carries the file. */
    public function field(): string
    {
        return match ($this) {
            self::Image => 'photo',
            default => $this->value,
        };
    }

    public function supportsCaption(): bool
    {
        return ! in_array($this, [self::Sticker, self::Location, self::Contact], true);
    }
}

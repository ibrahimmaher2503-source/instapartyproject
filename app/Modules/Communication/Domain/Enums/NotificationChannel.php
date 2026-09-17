<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Enums;

enum NotificationChannel: string
{
    case Push = 'push';
    case Sms = 'sms';
    case Whatsapp = 'whatsapp';
    case Email = 'email';
    case InApp = 'in_app';

    public function label(): string
    {
        return __('communication.notification_channel.'.$this->value);
    }
}

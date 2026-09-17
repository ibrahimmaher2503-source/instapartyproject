<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Timeline\Enums;

enum TimelineActorRole: string
{
    case Admin = 'admin';
    case Vendor = 'vendor';
    case Customer = 'customer';
    case System = 'system';
    case Webhook = 'webhook';
}

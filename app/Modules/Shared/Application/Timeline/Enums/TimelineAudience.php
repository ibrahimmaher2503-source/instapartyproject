<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Timeline\Enums;

enum TimelineAudience: string
{
    case Admin = 'admin';
    case Vendor = 'vendor';
    case Customer = 'customer'; // reserved; no v1 mount
}

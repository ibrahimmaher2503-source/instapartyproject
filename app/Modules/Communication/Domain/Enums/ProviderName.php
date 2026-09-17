<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Enums;

enum ProviderName: string
{
    case Firebase = 'firebase';
    case SmsMisr = 'sms_misr';
}

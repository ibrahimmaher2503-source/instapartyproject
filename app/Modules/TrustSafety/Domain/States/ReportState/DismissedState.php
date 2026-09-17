<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Domain\States\ReportState;

final class DismissedState extends ReportState
{
    public static string $name = 'dismissed';
}

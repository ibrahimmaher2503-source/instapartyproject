<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Enums;

use Filament\Support\Contracts\HasLabel;

enum SettlementRunStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Reconciled = 'reconciled';
    case Disputed = 'disputed';

    public function getLabel(): string
    {
        return __("settlement.settlement_run_status.{$this->value}");
    }
}

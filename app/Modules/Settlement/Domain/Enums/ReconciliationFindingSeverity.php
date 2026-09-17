<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReconciliationFindingSeverity: string implements HasLabel
{
    case Info = 'info';
    case Warning = 'warning';
    case High = 'high';

    public function label(): string
    {
        return __('settlement.finding_severity.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}

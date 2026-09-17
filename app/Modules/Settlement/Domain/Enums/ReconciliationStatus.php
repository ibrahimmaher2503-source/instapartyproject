<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReconciliationStatus: string implements HasLabel
{
    case Queued = 'queued';
    case Running = 'running';
    case Clean = 'clean';
    case AnomaliesDetected = 'anomalies_detected';
    case Repaired = 'repaired';
    case RequiresManualReview = 'requires_manual_review';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return __('settlement.reconciliation_status.'.$this->value);
    }
}

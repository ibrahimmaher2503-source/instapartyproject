<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Enums;

use Filament\Support\Contracts\HasLabel;

enum InvoiceStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case PastDue = 'past_due';

    public function getLabel(): string
    {
        return __('subscriptions::subscription.invoice_status.'.$this->value);
    }
}

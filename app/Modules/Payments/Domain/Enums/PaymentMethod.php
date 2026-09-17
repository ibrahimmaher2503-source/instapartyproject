<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Card = 'card';
    case Wallet = 'wallet';
    case Installment = 'installment';
    case CashOnDelivery = 'cash_on_delivery';
    case Transfer = 'transfer';

    public function getLabel(): string
    {
        return __("payments::payments.method.{$this->value}");
    }
}

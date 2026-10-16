<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Actions;

use App\Modules\Payments\Filament\Actions\Concerns\ConfiguresRefundPaymentAction;
use Filament\Actions\Action;

final class RefundPaymentHeaderAction extends Action
{
    use ConfiguresRefundPaymentAction;

    public static function getDefaultName(): ?string
    {
        return 'refund';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureRefundPaymentAction();
    }
}

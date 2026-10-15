<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Contracts;

use App\Modules\Payments\Application\DTOs\InitiatePaymentDto;
use App\Modules\Payments\Application\DTOs\PaymentIntentDto;
use App\Modules\Payments\Application\DTOs\PaymobWebhookDto;
use App\Modules\Payments\Application\DTOs\PingResult;
use App\Modules\Payments\Application\DTOs\RefundResultDto;
use App\Modules\Payments\Application\DTOs\VoidResult;
use App\Modules\Payments\Domain\Models\Payment;
use Brick\Money\Money;

interface PaymentGateway
{
    public function initiate(InitiatePaymentDto $dto, ?string $merchantOrderId = null): PaymentIntentDto;

    public function verifyWebhookSignature(array $payload, string $signature): bool;

    public function parseWebhook(array $payload): PaymobWebhookDto;

    /** Resolve a Paymob order reference from a provider-authenticated inquiry. */
    public function resolveMerchantOrderReference(string $providerOrderId): ?string;

    public function refund(Payment $payment, Money $amount): RefundResultDto;

    public function void(string $gatewayRef): VoidResult;

    public function ping(): PingResult;

    public function getTodayCapturedCount(): int;
}

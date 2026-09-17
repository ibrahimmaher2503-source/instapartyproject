<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Modules\Payments\Application\DTOs\InitiatePaymentDto;
use App\Modules\Payments\Application\DTOs\PaymentIntentDto;
use App\Modules\Payments\Application\DTOs\PaymobWebhookDto;
use App\Modules\Payments\Application\DTOs\PingResult;
use App\Modules\Payments\Application\DTOs\RefundResultDto;
use App\Modules\Payments\Application\DTOs\VoidResult;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Models\Payment;
use Brick\Money\Money;
use LogicException;

final class FakePaymentGateway implements PaymentGateway
{
    public function initiate(InitiatePaymentDto $dto): PaymentIntentDto
    {
        throw new LogicException('Financial operations are disabled in the vendor lifecycle QA harness.');
    }

    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        return false;
    }

    public function parseWebhook(array $payload): PaymobWebhookDto
    {
        throw new LogicException('Webhook parsing is disabled in the vendor lifecycle QA harness.');
    }

    public function refund(Payment $payment, Money $amount): RefundResultDto
    {
        throw new LogicException('Refunds are disabled in the vendor lifecycle QA harness.');
    }

    public function void(string $gatewayRef): VoidResult
    {
        throw new LogicException('Voids are disabled in the vendor lifecycle QA harness.');
    }

    public function ping(): PingResult
    {
        throw new LogicException('External payment calls are disabled in the vendor lifecycle QA harness.');
    }

    public function getTodayCapturedCount(): int
    {
        return 0;
    }
}

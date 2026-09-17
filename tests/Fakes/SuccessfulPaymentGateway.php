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

/**
 * Deterministic, no-network gateway for the isolated testing/E2E runtime.
 *
 * Capture/refund/webhook behavior stays unavailable so this double can only
 * exercise payment initiation and its production persistence/idempotency path.
 */
final class SuccessfulPaymentGateway implements PaymentGateway
{
    public function initiate(InitiatePaymentDto $dto): PaymentIntentDto
    {
        return new PaymentIntentDto(
            gatewayRef: 'test-'.$dto->bookingPublicId,
            redirectUrl: 'https://testing.invalid/payments/'.$dto->bookingPublicId,
            rawResponse: [
                'gateway' => 'testing',
                'booking_public_id' => $dto->bookingPublicId,
                'amount_minor' => $dto->amount->getMinorAmount()->toInt(),
                'currency' => $dto->amount->getCurrency()->getCurrencyCode(),
            ],
        );
    }

    /** @param array<string, mixed> $payload */
    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        return false;
    }

    /** @param array<string, mixed> $payload */
    public function parseWebhook(array $payload): PaymobWebhookDto
    {
        throw new LogicException('Webhook operations are disabled in the testing gateway.');
    }

    public function refund(Payment $payment, Money $amount): RefundResultDto
    {
        throw new LogicException('Refund operations are disabled in the testing gateway.');
    }

    public function void(string $gatewayRef): VoidResult
    {
        throw new LogicException('Void operations are disabled in the testing gateway.');
    }

    public function ping(): PingResult
    {
        return new PingResult(true, 0, 'testing');
    }

    public function getTodayCapturedCount(): int
    {
        return 0;
    }
}

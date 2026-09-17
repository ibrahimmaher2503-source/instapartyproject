<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Gateways;

use App\Modules\Payments\Application\DTOs\InitiatePaymentDto;
use App\Modules\Payments\Application\DTOs\PaymentIntentDto;
use App\Modules\Payments\Application\DTOs\PaymobWebhookDto;
use App\Modules\Payments\Application\DTOs\PingResult;
use App\Modules\Payments\Application\DTOs\RefundResultDto;
use App\Modules\Payments\Application\DTOs\VoidResult;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Infrastructure\Support\RedactPciFields;
use Brick\Money\Money;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class PaymobGateway implements PaymentGateway
{
    /** @var list<string> */
    private const HMAC_FIELDS = [
        'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction',
        'id', 'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded',
        'is_standalone_payment', 'is_voided', 'order.id', 'owner', 'pending',
        'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success',
    ];

    public function initiate(InitiatePaymentDto $dto): PaymentIntentDto
    {
        $merchantOrderId = (string) Str::ulid();
        $amountCents = $dto->amount->getMinorAmount()->toInt();
        $client = $this->client();

        try {
            $auth = $client->post('auth/tokens', ['api_key' => $this->required('api_key')])->throw()->json();
            $authToken = (string) ($auth['token'] ?? '');
            if ($authToken === '') {
                throw new \RuntimeException('Paymob authentication did not return a token.');
            }

            $order = $client->withToken($authToken)->post('ecommerce/orders', [
                'auth_token' => $authToken,
                'delivery_needed' => false,
                'amount_cents' => $amountCents,
                'currency' => $dto->amount->getCurrency()->getCurrencyCode(),
                'merchant_order_id' => $merchantOrderId,
            ])->throw()->json();
            $orderId = (int) ($order['id'] ?? 0);
            if ($orderId < 1) {
                throw new \RuntimeException('Paymob order response did not contain an order id.');
            }

            $paymentKey = $client->withToken($authToken)->post('acceptance/payment_keys', [
                'auth_token' => $authToken,
                'amount_cents' => $amountCents,
                'expiration' => 3600,
                'order_id' => $orderId,
                'billing_data' => $dto->billingData,
                'currency' => $dto->amount->getCurrency()->getCurrencyCode(),
                'integration_id' => (int) $this->required('integration_id'),
            ])->throw()->json();
            $paymentToken = (string) ($paymentKey['token'] ?? '');
            if ($paymentToken === '') {
                throw new \RuntimeException('Paymob payment-key response did not contain a token.');
            }

            return new PaymentIntentDto(
                gatewayRef: $merchantOrderId,
                redirectUrl: $this->clientUrl('acceptance/iframes/'.$this->required('iframe_id').'?payment_token='.urlencode($paymentToken)),
                rawResponse: RedactPciFields::redact([
                    'paymob_order_id' => $orderId,
                    'merchant_order_id' => $merchantOrderId,
                    'payment_key_token' => $paymentToken,
                ]),
            );
        } catch (Throwable $e) {
            throw new \RuntimeException('Paymob payment initiation failed: '.$e->getMessage(), 0, $e);
        }
    }

    /** @param array<string, mixed> $payload */
    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        $secret = (string) config('services.paymob.hmac_secret', '');
        if ($secret === '' || ! preg_match('/^[a-f0-9]{128}$/i', $signature)) {
            return false;
        }

        $values = array_map(fn (string $field): string => $this->stringify(data_get($payload, 'obj.'.$field)), self::HMAC_FIELDS);
        $expected = hash_hmac('sha512', implode('', $values), $secret);

        return hash_equals(strtolower($expected), strtolower($signature));
    }

    /** @param array<string, mixed> $payload */
    public function parseWebhook(array $payload): PaymobWebhookDto
    {
        $obj = (array) ($payload['obj'] ?? []);
        $success = (bool) ($obj['success'] ?? false);
        $failureCode = null;
        if (! $success) {
            foreach ([data_get($obj, 'data.message'), $obj['message'] ?? null, $obj['txn_response_code'] ?? null] as $candidate) {
                if (is_string($candidate) && trim($candidate) !== '') {
                    $failureCode = $candidate;
                    break;
                }
            }
            $failureCode ??= 'unknown';
        }

        return new PaymobWebhookDto(
            gatewayRef: (string) ($obj['id'] ?? ''),
            success: $success,
            failureCode: $failureCode,
            capturedAmount: Money::ofMinor((int) ($obj['amount_cents'] ?? 0), (string) ($obj['currency'] ?? 'EGP')),
            rawPayload: RedactPciFields::redact($payload),
            transactionType: ! empty($obj['is_refunded']) ? 'REFUND' : (! empty($obj['is_voided']) ? 'VOID' : 'PURCHASE'),
            orderId: isset($obj['order']['id']) ? (string) $obj['order']['id'] : null,
        );
    }

    public function refund(Payment $payment, Money $amount): RefundResultDto
    {
        return $this->mutateTransaction('acceptance/void_refund/refund', $payment, $amount);
    }

    public function void(string $gatewayRef): VoidResult
    {
        $transactionId = $gatewayRef;

        try {
            $token = $this->authenticate();
            $response = $this->client()->withToken($token)->post('acceptance/void_refund/void', [
                'transaction_id' => $transactionId,
            ]);

            if (! $response->successful()) {
                return new VoidResult(false, $gatewayRef, 'Paymob void request failed.');
            }

            return new VoidResult(true, (string) ($response->json('id') ?? $gatewayRef));
        } catch (Throwable) {
            return new VoidResult(false, $gatewayRef, 'Paymob void request failed.');
        }
    }

    public function ping(): PingResult
    {
        $start = microtime(true);
        $healthOrderId = config('services.paymob.health_check_order_id');
        if (! $healthOrderId || ! config('services.paymob.api_key')) {
            return new PingResult(false, 0, 'paymob', 'paymob_not_configured');
        }

        try {
            $this->client()->withToken($this->authenticate())->get('ecommerce/orders/'.rawurlencode((string) $healthOrderId))->throw();

            return new PingResult(true, (int) round((microtime(true) - $start) * 1000), 'paymob');
        } catch (Throwable $e) {
            return new PingResult(false, (int) round((microtime(true) - $start) * 1000), 'paymob', $e->getMessage());
        }
    }

    public function getTodayCapturedCount(): int
    {
        return GatewayWebhookLog::query()->where('event_type', 'transaction_processed')->whereNotNull('processed_at')->whereDate('created_at', Carbon::today())->count();
    }

    private function mutateTransaction(string $endpoint, Payment $payment, Money $amount): RefundResultDto
    {
        $transactionId = (string) ($payment->metadata['paymob_transaction_id'] ?? '');
        if ($transactionId === '') {
            return new RefundResultDto(false, null, 'Paymob transaction id is not available yet.');
        }

        try {
            $response = $this->client()->withToken($this->authenticate())->post($endpoint, [
                'transaction_id' => $transactionId,
                'amount_cents' => $amount->getMinorAmount()->toInt(),
            ]);
            if (! $response->successful()) {
                return new RefundResultDto(false, null, 'Paymob transaction mutation failed.');
            }

            return new RefundResultDto(true, (string) ($response->json('id') ?? $transactionId), null);
        } catch (Throwable) {
            return new RefundResultDto(false, null, 'Paymob transaction mutation failed.');
        }
    }

    private function authenticate(): string
    {
        $response = $this->client()->post('auth/tokens', ['api_key' => $this->required('api_key')])->throw();

        return (string) $response->json('token');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.paymob.api_url'), '/').'/')->acceptJson()->asJson()->timeout((int) config('services.paymob.timeout', 15));
    }

    private function clientUrl(string $path): string
    {
        return rtrim((string) config('services.paymob.checkout_url'), '/').'/'.$path;
    }

    private function required(string $key): string
    {
        $value = (string) config('services.paymob.'.$key, '');
        if ($value === '') {
            throw new \RuntimeException('paymob_'.$key.'_not_configured');
        }

        return $value;
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
        };
    }
}

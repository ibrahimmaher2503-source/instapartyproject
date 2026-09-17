<?php

declare(strict_types=1);

use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Filament\Resources\GatewayWebhookLogResource;

it('renders explicit webhook signature and processing states', function (): void {
    expect(GatewayWebhookLogResource::signatureStatus(true))->toBe(__('payments.signature_status.valid'))
        ->and(GatewayWebhookLogResource::signatureStatus(false))->toBe(__('payments.signature_status.invalid'))
        ->and(GatewayWebhookLogResource::signatureStatus(null))->toBe(__('payments.signature_status.not_checked'))
        ->and(GatewayWebhookLogResource::processingStatus(new GatewayWebhookLog([
            'signature_valid' => false,
        ])))->toBe(__('payments.processing_status.rejected'))
        ->and(GatewayWebhookLogResource::processingStatus(new GatewayWebhookLog([
            'signature_valid' => true,
            'processed_at' => now(),
        ])))->toBe(__('payments.processing_status.processed'))
        ->and(GatewayWebhookLogResource::processingStatus(new GatewayWebhookLog([
            'signature_valid' => true,
            'processing_error' => 'duplicate_idempotent_replay',
        ])))->toBe(__('payments.processing_status.duplicate'))
        ->and(GatewayWebhookLogResource::processingStatus(new GatewayWebhookLog([
            'signature_valid' => true,
            'processing_error' => 'legacy_failure',
        ])))->toBe(__('payments.processing_status.failed'))
        ->and(GatewayWebhookLogResource::processingStatus(new GatewayWebhookLog([
            'signature_valid' => null,
        ])))->toBe(__('payments.processing_status.pending'));
});

it('normalizes legacy webhook event spelling for display without changing stored evidence', function (): void {
    expect(GatewayWebhookLogResource::eventTypeLabel('T R A N S A C T I O N'))->toBe('Transaction')
        ->and(GatewayWebhookLogResource::eventTypeLabel('Refund Completed'))->toBe(__('payments.event_types.refund_completed'))
        ->and(GatewayWebhookLogResource::eventTypeLabel('payment.captured'))->toBe(__('payments.event_types.payment_captured'))
        ->and(GatewayWebhookLogResource::eventTypeLabel(null))->toBe(__('payments.event_types.unknown'));
});

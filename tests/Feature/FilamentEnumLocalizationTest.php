<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\ModificationProposalKind;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Promotions\Domain\Enums\PromoCodeScope;
use App\Modules\Promotions\Domain\Enums\PromoCodeType;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingSeverity;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingType;
use App\Modules\Settlement\Domain\Enums\ReconciliationStatus;
use App\Modules\Settlement\Domain\Enums\SettlementRunStatus;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Subscriptions\Domain\Enums\InvoiceStatus;
use App\Modules\Subscriptions\Domain\Enums\PlanCode;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionEventType;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use App\Modules\Support\Domain\Enums\SupportTicketStatus;

it('localizes every enum rendered directly by Filament', function (): void {
    $enums = [
        ProductType::class, ServiceStatus::class, ModificationProposalKind::class, ModificationStatus::class,
        PaymentMethod::class, PromoCodeScope::class, PromoCodeType::class,
        ModerationStatus::class, ReconciliationFindingSeverity::class, ReconciliationFindingType::class,
        ReconciliationStatus::class, SettlementRunStatus::class, TransactionKind::class, InvoiceStatus::class, PlanCode::class,
        SubscriptionEventType::class, SubscriptionStatus::class, SupportTicketStatus::class,
    ];

    foreach ($enums as $enum) {
        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);

            foreach ($enum::cases() as $case) {
                $label = $case->getLabel();

                $this->assertNotSame($case->value, $label, "{$enum} must have a localized label");
                $this->assertStringNotContainsString('::', $label);
                $this->assertDoesNotMatchRegularExpression('/^[a-z0-9_.]+$/', $label);
            }
        }
    }
});

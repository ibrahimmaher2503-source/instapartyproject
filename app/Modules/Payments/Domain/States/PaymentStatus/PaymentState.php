<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\States\PaymentStatus;

use App\Modules\Payments\Domain\States\PaymentStatus\Transitions\AbandonPaymentTransition;
use App\Modules\Payments\Domain\States\PaymentStatus\Transitions\AuthorizePaymentTransition;
use App\Modules\Payments\Domain\States\PaymentStatus\Transitions\CapturePaymentTransition;
use App\Modules\Payments\Domain\States\PaymentStatus\Transitions\FailPaymentTransition;
use App\Modules\Payments\Domain\States\PaymentStatus\Transitions\PartiallyRefundPaymentTransition;
use App\Modules\Payments\Domain\States\PaymentStatus\Transitions\RefundPaymentTransition;
use App\Modules\Payments\Domain\States\PaymentStatus\Transitions\RetryPaymentTransition;
use App\Modules\Payments\Domain\States\PaymentStatus\Transitions\VoidPaymentTransition;
use App\Modules\Shared\Domain\Contracts\TranslatableState;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class PaymentState extends State implements TranslatableState
{
    public static function label(string $stateName, string $locale): ?string
    {
        $key = "payments::payments.status.{$stateName}";
        $translated = __($key, [], $locale);

        if ($translated !== $key) {
            return $translated;
        }

        // Hard-coded fallback for all known payment gateway state names
        return match ($stateName) {
            'pending' => $locale === 'ar' ? 'قيد الانتظار' : 'Pending',
            'authorized' => $locale === 'ar' ? 'تم التفويض' : 'Authorized',
            'captured' => $locale === 'ar' ? 'تم التحصيل' : 'Captured',
            'failed' => $locale === 'ar' ? 'فشل' : 'Failed',
            'voided' => $locale === 'ar' ? 'ملغى' : 'Voided',
            'abandoned' => $locale === 'ar' ? 'متروك' : 'Abandoned',
            'partially_refunded' => $locale === 'ar' ? 'تم رد المبلغ جزئيًا' : 'Partially Refunded',
            'refunded' => $locale === 'ar' ? 'تم رد المبلغ' : 'Refunded',
            default => null,
        };
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingState::class)
            ->allowTransition(PendingState::class, AuthorizedState::class, AuthorizePaymentTransition::class)
            ->allowTransition(PendingState::class, FailedState::class, FailPaymentTransition::class)
            ->allowTransition(PendingState::class, AbandonedState::class, AbandonPaymentTransition::class)
            ->allowTransition(PendingState::class, CapturedState::class, CapturePaymentTransition::class)
            ->allowTransition(AuthorizedState::class, CapturedState::class, CapturePaymentTransition::class)
            ->allowTransition(AuthorizedState::class, VoidedState::class, VoidPaymentTransition::class)
            ->allowTransition(CapturedState::class, PartiallyRefundedState::class, PartiallyRefundPaymentTransition::class)
            ->allowTransition(CapturedState::class, RefundedState::class, RefundPaymentTransition::class)
            ->allowTransition(PartiallyRefundedState::class, RefundedState::class, RefundPaymentTransition::class)
            ->allowTransition(FailedState::class, PendingState::class, RetryPaymentTransition::class);
    }
}

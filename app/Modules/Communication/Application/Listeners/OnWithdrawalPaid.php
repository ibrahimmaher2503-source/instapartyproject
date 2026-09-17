<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Settlement\Domain\Events\WithdrawalPaid;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class OnWithdrawalPaid implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(WithdrawalPaid $event): void
    {
        $userId = DB::table('vendor_profiles')
            ->where('id', $event->vendorProfileId)
            ->value('user_id');

        if ($userId === null) {
            return;
        }

        $withdrawal = DB::table('withdrawals')
            ->where('id', $event->withdrawalId)
            ->select(['paid_amount_minor', 'paid_amount_currency', 'requested_amount_minor', 'requested_amount_currency'])
            ->first();

        $amountMinor = $withdrawal?->paid_amount_minor ?? $withdrawal?->requested_amount_minor ?? 0;
        $currency = $withdrawal?->paid_amount_currency ?? $withdrawal?->requested_amount_currency ?? 'EGP';

        $context = [
            'amount' => number_format($amountMinor / 100, 2),
            'currency' => $currency,
            'reference' => $event->withdrawalPublicId,
        ];

        foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'withdrawal.paid',
                channel: $channel,
                audience: NotificationAudience::Vendor,
                eventCategory: EventCategory::Payment,
                userId: $userId,
                context: $context,
                referenceType: 'withdrawal',
                referenceId: $event->withdrawalId,
            ));
        }
    }
}

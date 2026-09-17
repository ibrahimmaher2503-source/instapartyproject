<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Settlement\Domain\Events\WithdrawalRejected;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class OnWithdrawalRejected implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(WithdrawalRejected $event): void
    {
        $userId = DB::table('vendor_profiles')
            ->where('id', $event->vendorProfileId)
            ->value('user_id');

        if ($userId === null) {
            return;
        }

        $withdrawal = DB::table('withdrawals')
            ->where('id', $event->withdrawalId)
            ->select(['requested_amount_minor', 'requested_amount_currency'])
            ->first();

        $context = [
            'amount' => number_format(($withdrawal?->requested_amount_minor ?? 0) / 100, 2),
            'currency' => $withdrawal?->requested_amount_currency ?? 'EGP',
            'withdrawal_id' => $event->withdrawalPublicId,
        ];

        foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'withdrawal.rejected',
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

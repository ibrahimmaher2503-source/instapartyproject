<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Settlement\Domain\Events\WithdrawalApproved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class OnWithdrawalApproved implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(WithdrawalApproved $event): void
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

        $amount = $withdrawal !== null
            ? number_format($withdrawal->requested_amount_minor / 100, 2)
            : '0.00';
        $currency = $withdrawal?->requested_amount_currency ?? 'EGP';

        $context = [
            'amount' => $amount,
            'currency' => $currency,
            'withdrawal_id' => $event->withdrawalPublicId,
            'days' => '3–5',
        ];

        foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'withdrawal.approved',
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

<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Seeders;

use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

final class UnifiedLifecycleNotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['vendor.profile.approved', 'vendor.profile.rejected', 'vendor.product_type.approved'] as $eventKey) {
            foreach ([NotificationChannel::InApp, NotificationChannel::Sms] as $channel) {
                $approved = $eventKey !== 'vendor.profile.rejected';
                $body = $approved
                    ? ['en' => 'Your InstaParty vendor account is approved.', 'ar' => 'تم اعتماد حساب البائع الخاص بك على InstaParty.']
                    : ['en' => 'Your vendor application needs attention. Open InstaParty to review the reason.', 'ar' => 'يحتاج طلب البائع الخاص بك إلى إجراء. افتح InstaParty لمعرفة السبب.'];

                if ($eventKey === 'vendor.product_type.approved') {
                    $body = ['en' => 'You are approved to offer {{product_type}} services.', 'ar' => 'تم اعتمادك لتقديم خدمات {{product_type}}.'];
                }

                NotificationTemplate::query()->updateOrCreate(
                    ['event_key' => $eventKey, 'channel' => $channel->value, 'audience' => NotificationAudience::Vendor->value],
                    [
                        'public_id' => str()->ulid()->toBase32(),
                        'subject' => $channel === NotificationChannel::InApp
                            ? ['en' => $approved ? 'Vendor account approved' : 'Vendor application needs attention', 'ar' => $approved ? 'تم اعتماد حساب البائع' : 'طلب البائع يحتاج إلى إجراء']
                            : ['en' => null, 'ar' => null],
                        'body' => $body,
                        'variables' => ['vendor_name', 'product_type', 'rejection_reason'],
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}

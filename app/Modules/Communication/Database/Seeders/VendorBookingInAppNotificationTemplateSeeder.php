<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Seeders;

use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

final class VendorBookingInAppNotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            [
                'event_key' => 'booking.submitted',
                'subject' => ['en' => 'New booking request', 'ar' => 'طلب حجز جديد'],
                'body' => [
                    'en' => 'New booking request #{{booking_number}}. Review and respond before {{response_deadline}}.',
                    'ar' => 'طلب حجز جديد رقم #{{booking_number}}. راجع الطلب ورد قبل {{response_deadline}}.',
                ],
                'variables' => ['booking_number', 'response_deadline'],
            ],
            [
                'event_key' => 'booking.force_cancelled.vendor',
                'subject' => ['en' => 'Booking request cancelled', 'ar' => 'تم إلغاء طلب الحجز'],
                'body' => [
                    'en' => 'The InstaParty team cancelled booking request #{{booking_number}}. No response is due.',
                    'ar' => 'ألغى فريق InstaParty طلب الحجز رقم #{{booking_number}}. لا يلزم الرد على هذا الطلب.',
                ],
                'variables' => ['booking_number'],
            ],
        ] as $template) {
            NotificationTemplate::query()->firstOrCreate([
                'event_key' => $template['event_key'],
                'channel' => NotificationChannel::InApp->value,
                'audience' => NotificationAudience::Vendor->value,
            ], [
                'subject' => $template['subject'],
                'body' => $template['body'],
                'variables' => $template['variables'],
                'is_active' => true,
            ]);
        }
    }
}

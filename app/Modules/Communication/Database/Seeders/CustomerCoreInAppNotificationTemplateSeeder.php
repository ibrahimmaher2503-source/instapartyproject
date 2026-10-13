<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Seeders;

use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

final class CustomerCoreInAppNotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'booking.confirmed' => [
                'subject' => ['en' => 'Booking confirmed', 'ar' => 'تم تأكيد الحجز'],
                'body' => ['en' => 'Your booking #{{booking_id}} has been confirmed.', 'ar' => 'تم تأكيد حجزك رقم #{{booking_id}}.'],
                'variables' => ['booking_id'],
            ],
            'booking.cancelled' => [
                'subject' => ['en' => 'Booking cancelled', 'ar' => 'تم إلغاء الحجز'],
                'body' => ['en' => 'Your booking #{{booking_id}} has been cancelled.', 'ar' => 'تم إلغاء حجزك رقم #{{booking_id}}.'],
                'variables' => ['booking_id'],
            ],
            'booking.rejected' => [
                'subject' => ['en' => 'A vendor declined your booking', 'ar' => 'اعتذر مورد عن حجزك'],
                'body' => ['en' => '{{vendor_name}} could not take your booking #{{booking_id}}. {{rejection_reason}}', 'ar' => 'لم يتمكن {{vendor_name}} من قبول حجزك رقم #{{booking_id}}. {{rejection_reason}}'],
                'variables' => ['booking_id', 'vendor_name', 'rejection_reason'],
            ],
            'payment.captured' => [
                'subject' => ['en' => 'Payment confirmed', 'ar' => 'تم تأكيد الدفع'],
                'body' => ['en' => 'Payment of {{amount}} confirmed for booking #{{booking_id}}.', 'ar' => 'تم تأكيد دفع {{amount}} للحجز رقم #{{booking_id}}.'],
                'variables' => ['amount', 'booking_id'],
            ],
            'refund.completed' => [
                'subject' => ['en' => 'Refund completed', 'ar' => 'تم استرداد المبلغ'],
                'body' => ['en' => 'Your refund of {{amount}} {{currency}} for booking #{{booking_number}} has been completed.', 'ar' => 'تم استرداد مبلغ {{amount}} {{currency}} للحجز رقم #{{booking_number}}.'],
                'variables' => ['booking_number', 'amount', 'currency'],
            ],
        ];

        foreach ($templates as $eventKey => $template) {
            NotificationTemplate::query()->firstOrCreate(
                ['event_key' => $eventKey, 'channel' => NotificationChannel::InApp->value, 'audience' => NotificationAudience::Customer->value],
                array_merge($template, ['is_active' => true]),
            );
        }
    }
}

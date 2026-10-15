<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Seeders;

use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

final class AlternativeVendorInAppNotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            [
                'event_key' => 'booking.alternative_vendor.proposed',
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => 'Alternative vendor proposed', 'ar' => 'تم اقتراح مورد بديل'],
                'body' => [
                    'en' => '{{vendor_name}} was proposed for booking {{booking_reference}}. Review the proposal: {{action_url}}',
                    'ar' => 'تم اقتراح {{vendor_name}} للحجز {{booking_reference}}. راجع الاقتراح: {{action_url}}',
                ],
                'variables' => ['booking_reference', 'proposal_public_id', 'action_url', 'vendor_name'],
            ],
            ...array_map(static fn (NotificationAudience $audience): array => [
                'event_key' => 'booking.alternative_vendor.decided',
                'audience' => $audience,
                'subject' => ['en' => 'Alternative vendor decision recorded', 'ar' => 'تم تسجيل قرار المورد البديل'],
                'body' => [
                    'en' => 'A decision ({{decision}}) was recorded for the alternative vendor proposal on booking {{booking_reference}}. Affected vendor: {{vendor_name}}. Review: {{action_url}}',
                    'ar' => 'تم تسجيل القرار ({{decision}}) بشأن اقتراح المورد البديل للحجز {{booking_reference}}. المورد المعني: {{vendor_name}}. راجع: {{action_url}}',
                ],
                'variables' => ['booking_reference', 'proposal_public_id', 'decision', 'action_url', 'vendor_name'],
            ], [NotificationAudience::Customer, NotificationAudience::Vendor]),
        ] as $template) {
            NotificationTemplate::query()->firstOrCreate([
                'event_key' => $template['event_key'],
                'channel' => NotificationChannel::InApp->value,
                'audience' => $template['audience']->value,
            ], [
                'subject' => $template['subject'],
                'body' => $template['body'],
                'variables' => $template['variables'],
                'is_active' => true,
            ]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Communication\Database\Seeders;

use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;

final class GapNotificationTemplateSeeder extends Seeder
{
    use SeedsDevelopmentData;

    public function run(): void
    {
        fake()->seed(2026060800);

        foreach ($this->templates() as $template) {
            $this->updateOrCreateFactoryModel(
                NotificationTemplate::factory()->make([
                    'public_id' => $this->stablePublicId('notification-template:'.$template['event_key'].':'.$template['channel']->value.':'.$template['audience']->value),
                    'event_key' => $template['event_key'],
                    'channel' => $template['channel'],
                    'audience' => $template['audience'],
                    'subject' => $template['subject'],
                    'body' => $template['body'],
                    'variables' => $template['variables'],
                    'is_active' => true,
                ]),
                [
                    'event_key' => $template['event_key'],
                    'channel' => $template['channel']->value,
                    'audience' => $template['audience']->value,
                ],
            );
        }
    }

    /**
     * @return array<int, array{
     *     event_key: string,
     *     channel: NotificationChannel,
     *     audience: NotificationAudience,
     *     subject: array<string, string|null>,
     *     body: array<string, string>,
     *     variables: array<int, string>
     * }>
     */
    private function templates(): array
    {
        return [
            // ── Vendor profile lifecycle ──────────────────────────────────────

            [
                'event_key' => 'vendor.profile.approved',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => '🎉 Your vendor profile has been approved! You can now create services.', 'ar' => '🎉 تم قبول ملفك كمورد! يمكنك الآن إنشاء الخدمات.'],
                'variables' => ['vendor_name'],
            ],
            [
                'event_key' => 'vendor.profile.approved',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Your vendor profile is approved', 'ar' => 'تم قبول ملفك كمورد'],
                'body' => ['en' => 'Hi {{vendor_name}}, your vendor profile has been reviewed and approved. You can now publish services on InstaParty.', 'ar' => 'مرحباً {{vendor_name}}، تمت مراجعة ملفك وقبوله. يمكنك الآن نشر الخدمات على InstaParty.'],
                'variables' => ['vendor_name'],
            ],
            [
                'event_key' => 'vendor.profile.rejected',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Your vendor profile was not approved. Tap to see the reason and resubmit.', 'ar' => 'لم يتم قبول ملفك كمورد. اضغط لمعرفة السبب وإعادة التقديم.'],
                'variables' => ['vendor_name', 'rejection_reason'],
            ],
            [
                'event_key' => 'vendor.profile.rejected',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Your vendor application was not approved', 'ar' => 'لم يتم قبول طلبك كمورد'],
                'body' => ['en' => 'Hi {{vendor_name}}, unfortunately your vendor profile was not approved. Reason: {{rejection_reason}}. Please correct the issues and resubmit.', 'ar' => 'مرحباً {{vendor_name}}، للأسف لم يتم قبول ملفك. السبب: {{rejection_reason}}. يرجى تصحيح المشكلات وإعادة التقديم.'],
                'variables' => ['vendor_name', 'rejection_reason'],
            ],
            [
                'event_key' => 'vendor.profile.suspended',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Your vendor account has been suspended. Tap to learn more.', 'ar' => 'تم تعليق حسابك كمورد. اضغط لمعرفة التفاصيل.'],
                'variables' => ['vendor_name'],
            ],
            [
                'event_key' => 'vendor.profile.suspended',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Your vendor account has been suspended', 'ar' => 'تم تعليق حسابك كمورد'],
                'body' => ['en' => 'Hi {{vendor_name}}, your vendor account has been suspended by our team. Please contact support for more information.', 'ar' => 'مرحباً {{vendor_name}}، تم تعليق حسابك من قِبل فريقنا. يرجى التواصل مع الدعم لمزيد من المعلومات.'],
                'variables' => ['vendor_name'],
            ],
            [
                'event_key' => 'vendor.profile.auto_suspended',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Your account has been suspended — {{doc_type}} expired on {{expiry_date}}. Please renew and resubmit.', 'ar' => 'تم تعليق حسابك — انتهت صلاحية {{doc_type}} بتاريخ {{expiry_date}}. يرجى التجديد وإعادة الرفع.'],
                'variables' => ['vendor_name', 'doc_type', 'expiry_date'],
            ],
            [
                'event_key' => 'vendor.profile.auto_suspended',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Account suspended — document expired', 'ar' => 'تم تعليق الحساب — مستند منتهي الصلاحية'],
                'body' => ['en' => 'Hi {{vendor_name}}, your account was automatically suspended because {{doc_type}} expired on {{expiry_date}}. Upload a renewed document to restore your account.', 'ar' => 'مرحباً {{vendor_name}}، تم تعليق حسابك تلقائياً لأن {{doc_type}} انتهت صلاحيته في {{expiry_date}}. ارفع المستند المجدَّد لاستعادة حسابك.'],
                'variables' => ['vendor_name', 'doc_type', 'expiry_date'],
            ],
            [
                'event_key' => 'vendor.profile.auto_suspended',
                'channel' => NotificationChannel::Sms,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'InstaParty: Your account was suspended — {{doc_type}} expired {{expiry_date}}. Login to upload a renewal.', 'ar' => 'InstaParty: تم تعليق حسابك — انتهت {{doc_type}} بتاريخ {{expiry_date}}. سجّل الدخول لرفع التجديد.'],
                'variables' => ['doc_type', 'expiry_date'],
            ],
            [
                'event_key' => 'vendor.profile.reinstated',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Great news! Your vendor account has been reinstated. Welcome back!', 'ar' => 'أخبار رائعة! تم إعادة تفعيل حسابك كمورد. مرحباً بعودتك!'],
                'variables' => ['vendor_name'],
            ],
            [
                'event_key' => 'vendor.profile.reinstated',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Your vendor account has been reinstated', 'ar' => 'تم إعادة تفعيل حسابك كمورد'],
                'body' => ['en' => 'Hi {{vendor_name}}, your vendor account has been reviewed and reinstated. Your services are active again.', 'ar' => 'مرحباً {{vendor_name}}، تمت مراجعة حسابك وإعادة تفعيله. خدماتك نشطة مجدداً.'],
                'variables' => ['vendor_name'],
            ],

            // ── Vendor product-type approvals ─────────────────────────────────

            [
                'event_key' => 'vendor.product_type.approved',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'You\'re now approved to list {{product_type}} services on InstaParty!', 'ar' => 'تم اعتمادك الآن لإدراج خدمات {{product_type}} على InstaParty!'],
                'variables' => ['vendor_name', 'product_type'],
            ],
            [
                'event_key' => 'vendor.product_type.approved',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'You\'re approved for {{product_type}} services', 'ar' => 'تمت الموافقة على خدمات {{product_type}} لديك'],
                'body' => ['en' => 'Hi {{vendor_name}}, you\'ve been approved to create and publish {{product_type}} services on InstaParty.', 'ar' => 'مرحباً {{vendor_name}}، تمت الموافقة على إنشاء ونشر خدمات {{product_type}} على InstaParty.'],
                'variables' => ['vendor_name', 'product_type'],
            ],
            [
                'event_key' => 'vendor.product_type.revoked',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Your approval for {{product_type}} services has been revoked. Tap for details.', 'ar' => 'تم سحب اعتمادك لخدمات {{product_type}}. اضغط للتفاصيل.'],
                'variables' => ['product_type', 'reason'],
            ],
            [
                'event_key' => 'vendor.product_type.revoked',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Your {{product_type}} approval has been revoked', 'ar' => 'تم سحب اعتماد {{product_type}} الخاص بك'],
                'body' => ['en' => 'Your approval to list {{product_type}} services has been revoked. Reason: {{reason}}. Contact support if you believe this is an error.', 'ar' => 'تم سحب اعتمادك لنشر خدمات {{product_type}}. السبب: {{reason}}. تواصل مع الدعم إذا كنت تعتقد أن هذا خطأ.'],
                'variables' => ['product_type', 'reason'],
            ],

            // ── Vendor documents ──────────────────────────────────────────────

            [
                'event_key' => 'vendor.document.approved',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Your {{doc_type}} document has been approved.', 'ar' => 'تمت الموافقة على مستند {{doc_type}} الخاص بك.'],
                'variables' => ['doc_type'],
            ],
            [
                'event_key' => 'vendor.document.approved',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Document approved: {{doc_type}}', 'ar' => 'تمت الموافقة على المستند: {{doc_type}}'],
                'body' => ['en' => 'Your submitted {{doc_type}} document has been reviewed and approved by our team.', 'ar' => 'تمت مراجعة مستند {{doc_type}} الذي رفعته والموافقة عليه من قِبل فريقنا.'],
                'variables' => ['doc_type'],
            ],
            [
                'event_key' => 'vendor.document.rejected',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Your {{doc_type}} document was rejected. Tap to see the notes and resubmit.', 'ar' => 'تم رفض مستند {{doc_type}} الخاص بك. اضغط لعرض الملاحظات وإعادة الرفع.'],
                'variables' => ['doc_type', 'review_notes'],
            ],
            [
                'event_key' => 'vendor.document.rejected',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Document rejected: {{doc_type}}', 'ar' => 'تم رفض المستند: {{doc_type}}'],
                'body' => ['en' => 'Your {{doc_type}} document was rejected. Notes: {{review_notes}}. Please upload a corrected version.', 'ar' => 'تم رفض مستند {{doc_type}} الخاص بك. الملاحظات: {{review_notes}}. يرجى رفع نسخة مصحَّحة.'],
                'variables' => ['doc_type', 'review_notes'],
            ],

            // ── Customer lifecycle ────────────────────────────────────────────

            [
                'event_key' => 'customer.welcome',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => 'Welcome to InstaParty! 🎉', 'ar' => 'أهلاً بك في InstaParty! 🎉'],
                'body' => ['en' => 'Hi {{first_name}}, welcome to InstaParty! Start planning your perfect event today — browse vendors, compare prices, and book with confidence.', 'ar' => 'مرحباً {{first_name}}، أهلاً بك في InstaParty! ابدأ التخطيط لحفلتك المثالية اليوم — تصفّح الموردين وقارن الأسعار واحجز بثقة.'],
                'variables' => ['first_name'],
            ],
            [
                'event_key' => 'customer.suspended',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => 'Your account has been suspended', 'ar' => 'تم تعليق حسابك'],
                'body' => ['en' => 'Your InstaParty account has been suspended due to a violation of our terms. Please contact support for assistance.', 'ar' => 'تم تعليق حسابك على InstaParty بسبب انتهاك الشروط. يرجى التواصل مع الدعم للمساعدة.'],
                'variables' => [],
            ],
            [
                'event_key' => 'customer.suspended',
                'channel' => NotificationChannel::InApp,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Your account has been suspended. Contact support for assistance.', 'ar' => 'تم تعليق حسابك. تواصل مع الدعم للمساعدة.'],
                'variables' => [],
            ],

            // ── Booking notifications ─────────────────────────────────────────

            [
                'event_key' => 'booking.modification_proposed',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => '{{vendor_name}} proposed changes to your booking #{{booking_number}}. Review before {{deadline_at}}.', 'ar' => 'اقترح {{vendor_name}} تعديلات على حجزك رقم #{{booking_number}}. راجعها قبل {{deadline_at}}.'],
                'variables' => ['booking_number', 'vendor_name', 'deadline_at'],
            ],
            [
                'event_key' => 'booking.modification_proposed',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => 'Vendor proposed changes to your booking', 'ar' => 'اقترح المورد تعديلات على حجزك'],
                'body' => ['en' => '{{vendor_name}} has proposed changes to booking #{{booking_number}}. Please review and accept or decline before {{deadline_at}}.', 'ar' => 'اقترح {{vendor_name}} تعديلات على الحجز رقم #{{booking_number}}. يرجى المراجعة والقبول أو الرفض قبل {{deadline_at}}.'],
                'variables' => ['booking_number', 'vendor_name', 'deadline_at'],
            ],
            [
                'event_key' => 'booking.force_cancelled',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Booking #{{booking_number}} has been cancelled by our team. A refund will be processed in {{refund_days}} business days.', 'ar' => 'تم إلغاء الحجز رقم #{{booking_number}} من قِبل فريقنا. سيتم معالجة الاسترداد خلال {{refund_days}} أيام عمل.'],
                'variables' => ['booking_number', 'event_date', 'reason', 'refund_days'],
            ],
            [
                'event_key' => 'booking.force_cancelled',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => 'Your booking #{{booking_number}} has been cancelled', 'ar' => 'تم إلغاء حجزك رقم #{{booking_number}}'],
                'body' => ['en' => 'We\'re sorry to inform you that booking #{{booking_number}} (event date: {{event_date}}) was cancelled by our team. Reason: {{reason}}. A full refund will be issued within {{refund_days}} business days.', 'ar' => 'نأسف لإبلاغك بأن الحجز رقم #{{booking_number}} (تاريخ الحدث: {{event_date}}) تم إلغاؤه من قِبل فريقنا. السبب: {{reason}}. سيُعاد المبلغ كاملاً خلال {{refund_days}} أيام عمل.'],
                'variables' => ['booking_number', 'event_date', 'reason', 'refund_days'],
            ],
            [
                'event_key' => 'booking.force_cancelled.vendor',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Booking #{{booking_number}} from {{customer_name}} was cancelled by the InstaParty team.', 'ar' => 'تم إلغاء الحجز رقم #{{booking_number}} من {{customer_name}} من قِبل فريق InstaParty.'],
                'variables' => ['booking_number', 'customer_name', 'reason'],
            ],
            [
                'event_key' => 'booking.vendor_timeout',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'A vendor did not respond to booking #{{booking_number}} in time. Our team is handling this.', 'ar' => 'لم يستجب المورد للحجز رقم #{{booking_number}} في الوقت المحدد. فريقنا يعالج الأمر.'],
                'variables' => ['booking_number', 'event_date'],
            ],
            [
                'event_key' => 'booking.vendor_timeout',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => 'Vendor did not respond to your booking', 'ar' => 'لم يستجب المورد لحجزك'],
                'body' => ['en' => 'A vendor did not respond to booking #{{booking_number}} (event: {{event_date}}) within the required time. Our team has been notified and will resolve this promptly.', 'ar' => 'لم يستجب المورد للحجز رقم #{{booking_number}} (الحدث: {{event_date}}) في الوقت المطلوب. تم إخطار فريقنا وسيتم حل الأمر قريباً.'],
                'variables' => ['booking_number', 'event_date'],
            ],
            [
                'event_key' => 'booking.vendor_response_timeout',
                'channel' => NotificationChannel::InApp,
                'audience' => NotificationAudience::Admin,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Vendor(s) did not respond to booking #{{booking_number}} within 24h SLA. Manual intervention required.', 'ar' => 'لم يستجب الموردون للحجز رقم #{{booking_number}} خلال 24 ساعة. يلزم التدخل اليدوي.'],
                'variables' => ['booking_number', 'event_date'],
            ],
            [
                'event_key' => 'booking.vendor_deadline_reminder',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => '⏰ Reminder: You have {{hours}} hours left to respond to booking #{{booking_number}} from {{customer_name}}.', 'ar' => '⏰ تذكير: لديك {{hours}} ساعات للرد على الحجز رقم #{{booking_number}} من {{customer_name}}.'],
                'variables' => ['booking_number', 'customer_name', 'hours', 'deadline_at'],
            ],
            [
                'event_key' => 'booking.vendor_deadline_reminder',
                'channel' => NotificationChannel::Sms,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'InstaParty: {{hours}}h left to respond to booking #{{booking_number}}. Deadline: {{deadline_at}}.', 'ar' => 'InstaParty: {{hours}} ساعة للرد على الحجز #{{booking_number}}. الموعد النهائي: {{deadline_at}}.'],
                'variables' => ['booking_number', 'hours', 'deadline_at'],
            ],
            [
                'event_key' => 'booking.event_reminder',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => '🎉 Your event is tomorrow! Booking #{{booking_number}} on {{event_date}}.', 'ar' => '🎉 حدثك غداً! الحجز رقم #{{booking_number}} بتاريخ {{event_date}}.'],
                'variables' => ['booking_number', 'event_date', 'event_location', 'vendor_count'],
            ],
            [
                'event_key' => 'booking.event_reminder',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => 'Your event is tomorrow — Booking #{{booking_number}}', 'ar' => 'حدثك غداً — الحجز رقم #{{booking_number}}'],
                'body' => ['en' => 'Your event is coming up tomorrow ({{event_date}}) at {{event_location}}. You have {{vendor_count}} vendor(s) confirmed. Everything is on track!', 'ar' => 'حدثك قادم غداً ({{event_date}}) في {{event_location}}. لديك {{vendor_count}} مورد/موردون مؤكدون. كل شيء على ما يرام!'],
                'variables' => ['booking_number', 'event_date', 'event_location', 'vendor_count'],
            ],
            [
                'event_key' => 'booking.event_reminder',
                'channel' => NotificationChannel::Sms,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'InstaParty: Your event is tomorrow! Booking #{{booking_number}} on {{event_date}}.', 'ar' => 'InstaParty: حدثك غداً! الحجز #{{booking_number}} بتاريخ {{event_date}}.'],
                'variables' => ['booking_number', 'event_date'],
            ],
            [
                'event_key' => 'booking.event_reminder',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => '📅 Reminder: You have a booking tomorrow — #{{booking_number}} on {{event_date}}.', 'ar' => '📅 تذكير: لديك حجز غداً — رقم #{{booking_number}} بتاريخ {{event_date}}.'],
                'variables' => ['booking_number', 'event_date'],
            ],
            [
                'event_key' => 'booking.event_reminder',
                'channel' => NotificationChannel::Sms,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'InstaParty: Booking #{{booking_number}} is tomorrow ({{event_date}}). Make sure you\'re prepared!', 'ar' => 'InstaParty: الحجز #{{booking_number}} غداً ({{event_date}}). تأكد من استعدادك!'],
                'variables' => ['booking_number', 'event_date'],
            ],

            // ── Payments ──────────────────────────────────────────────────────

            [
                'event_key' => 'payment.failed.customer',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Payment for booking #{{booking_number}} failed. Please update your payment method and try again.', 'ar' => 'فشل الدفع للحجز رقم #{{booking_number}}. يرجى تحديث وسيلة الدفع والمحاولة مجدداً.'],
                'variables' => ['booking_number', 'failure_reason'],
            ],
            [
                'event_key' => 'payment.failed.customer',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => 'Payment failed for booking #{{booking_number}}', 'ar' => 'فشل الدفع للحجز رقم #{{booking_number}}'],
                'body' => ['en' => 'We could not process your payment for booking #{{booking_number}}. Reason: {{failure_reason}}. Please log in and retry with a valid payment method.', 'ar' => 'لم نتمكن من معالجة دفعتك للحجز رقم #{{booking_number}}. السبب: {{failure_reason}}. يرجى تسجيل الدخول وإعادة المحاولة بوسيلة دفع صالحة.'],
                'variables' => ['booking_number', 'failure_reason'],
            ],
            [
                'event_key' => 'refund.completed',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Your refund of {{amount}} {{currency}} for booking #{{booking_number}} is on the way. Expect it in {{days}} business days.', 'ar' => 'استرداد مبلغ {{amount}} {{currency}} للحجز #{{booking_number}} في الطريق إليك. توقعه خلال {{days}} أيام عمل.'],
                'variables' => ['booking_number', 'amount', 'currency', 'days'],
            ],
            [
                'event_key' => 'refund.completed',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => 'Refund processed for booking #{{booking_number}}', 'ar' => 'تم معالجة الاسترداد للحجز رقم #{{booking_number}}'],
                'body' => ['en' => 'Your refund of {{amount}} {{currency}} for booking #{{booking_number}} has been initiated. It should appear in your account within {{days}} business days.', 'ar' => 'تمت معالجة استرداد {{amount}} {{currency}} للحجز #{{booking_number}}. يجب أن يظهر في حسابك خلال {{days}} أيام عمل.'],
                'variables' => ['booking_number', 'amount', 'currency', 'days'],
            ],
            [
                'event_key' => 'refund.failed.customer',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'We had trouble processing your refund for booking #{{booking_number}}. Our team is looking into it.', 'ar' => 'واجهنا مشكلة في معالجة استرداد الحجز رقم #{{booking_number}}. فريقنا يتابع الأمر.'],
                'variables' => ['booking_number', 'failure_reason'],
            ],
            [
                'event_key' => 'refund.failed.customer',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => 'Issue with your refund for booking #{{booking_number}}', 'ar' => 'مشكلة في استرداد الحجز رقم #{{booking_number}}'],
                'body' => ['en' => 'We were unable to process your refund for booking #{{booking_number}}. Reason: {{failure_reason}}. Our team has been alerted and will resolve this. You\'ll hear from us within 24 hours.', 'ar' => 'لم نتمكن من معالجة الاسترداد للحجز رقم #{{booking_number}}. السبب: {{failure_reason}}. تم تنبيه فريقنا وسيتم الحل. ستسمع منا خلال 24 ساعة.'],
                'variables' => ['booking_number', 'failure_reason'],
            ],
            [
                'event_key' => 'refund.failed',
                'channel' => NotificationChannel::InApp,
                'audience' => NotificationAudience::Admin,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Refund processing failed for booking #{{booking_number}}. Manual action required.', 'ar' => 'فشل معالجة الاسترداد للحجز رقم #{{booking_number}}. يلزم التدخل اليدوي.'],
                'variables' => ['booking_number', 'failure_reason'],
            ],
            [
                'event_key' => 'chargeback.opened',
                'channel' => NotificationChannel::InApp,
                'audience' => NotificationAudience::Admin,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Chargeback opened on booking #{{booking_number}} for {{amount}} {{currency}}. Review required.', 'ar' => 'تم فتح استرداد قسري للحجز رقم #{{booking_number}} بمبلغ {{amount}} {{currency}}. يلزم المراجعة.'],
                'variables' => ['booking_number', 'amount', 'currency'],
            ],

            // ── Withdrawals ───────────────────────────────────────────────────

            [
                'event_key' => 'withdrawal.approved',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Your withdrawal of {{amount}} {{currency}} has been approved. Expect payment in {{days}} business days.', 'ar' => 'تمت الموافقة على طلب سحبك بمبلغ {{amount}} {{currency}}. توقع الدفع خلال {{days}} أيام عمل.'],
                'variables' => ['amount', 'currency', 'withdrawal_id', 'days'],
            ],
            [
                'event_key' => 'withdrawal.approved',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Withdrawal #{{withdrawal_id}} approved', 'ar' => 'تمت الموافقة على السحب رقم #{{withdrawal_id}}'],
                'body' => ['en' => 'Your withdrawal request #{{withdrawal_id}} for {{amount}} {{currency}} has been approved and is being processed. You\'ll receive the funds within {{days}} business days.', 'ar' => 'تمت الموافقة على طلب السحب رقم #{{withdrawal_id}} بمبلغ {{amount}} {{currency}} وهو قيد المعالجة. ستستلم الأموال خلال {{days}} أيام عمل.'],
                'variables' => ['amount', 'currency', 'withdrawal_id', 'days'],
            ],
            [
                'event_key' => 'withdrawal.paid',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => '💰 {{amount}} {{currency}} has been sent to your bank account (ref: {{reference}}).', 'ar' => '💰 تم إرسال {{amount}} {{currency}} إلى حسابك البنكي (المرجع: {{reference}}).'],
                'variables' => ['amount', 'currency', 'reference'],
            ],
            [
                'event_key' => 'withdrawal.paid',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Withdrawal paid — {{amount}} {{currency}}', 'ar' => 'تم صرف السحب — {{amount}} {{currency}}'],
                'body' => ['en' => 'Your withdrawal of {{amount}} {{currency}} (reference: {{reference}}) has been paid to your registered bank account. Please allow 1–2 business days for it to appear.', 'ar' => 'تم صرف مبلغ {{amount}} {{currency}} (المرجع: {{reference}}) إلى حسابك البنكي المسجل. قد يستغرق الأمر 1–2 يوم عمل للظهور.'],
                'variables' => ['amount', 'currency', 'reference'],
            ],
            [
                'event_key' => 'withdrawal.rejected',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Your withdrawal request #{{withdrawal_id}} was rejected. Your balance has been restored. Tap for details.', 'ar' => 'تم رفض طلب السحب رقم #{{withdrawal_id}}. تمت استعادة رصيدك. اضغط للتفاصيل.'],
                'variables' => ['amount', 'currency', 'withdrawal_id'],
            ],
            [
                'event_key' => 'withdrawal.rejected',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Withdrawal #{{withdrawal_id}} rejected', 'ar' => 'تم رفض السحب رقم #{{withdrawal_id}}'],
                'body' => ['en' => 'Your withdrawal request #{{withdrawal_id}} for {{amount}} {{currency}} has been rejected and your balance restored. Please contact support if you have questions.', 'ar' => 'تم رفض طلب السحب رقم #{{withdrawal_id}} بمبلغ {{amount}} {{currency}} وتمت استعادة رصيدك. تواصل مع الدعم إذا كانت لديك أسئلة.'],
                'variables' => ['amount', 'currency', 'withdrawal_id'],
            ],

            // ── Trust & Safety ────────────────────────────────────────────────

            [
                'event_key' => 'trust_safety.report_submitted',
                'channel' => NotificationChannel::InApp,
                'audience' => NotificationAudience::Admin,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'New report #{{report_id}} submitted. Review required.', 'ar' => 'تم تقديم بلاغ جديد رقم #{{report_id}}. يلزم المراجعة.'],
                'variables' => ['report_id'],
            ],
            [
                'event_key' => 'report.submitted.ack',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => 'Your report has been received', 'ar' => 'تم استلام بلاغك'],
                'body' => ['en' => 'Thank you for your report (#{{report_id}}). Our Trust & Safety team will review it within 48 hours. We take all reports seriously.', 'ar' => 'شكراً على بلاغك (#{{report_id}}). سيراجعه فريق الثقة والسلامة لدينا خلال 48 ساعة. نأخذ جميع البلاغات بجدية.'],
                'variables' => ['report_id'],
            ],
            [
                'event_key' => 'report.submitted.ack',
                'channel' => NotificationChannel::InApp,
                'audience' => NotificationAudience::Customer,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Report #{{report_id}} received. Our team will review it within 48 hours.', 'ar' => 'تم استلام البلاغ رقم #{{report_id}}. فريقنا سيراجعه خلال 48 ساعة.'],
                'variables' => ['report_id'],
            ],

            // ── Catalog / Service moderation ──────────────────────────────────

            [
                'event_key' => 'service.published',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => '✅ Your {{product_type}} service "{{service_name}}" is now live and visible to customers!', 'ar' => '✅ خدمتك {{product_type}} "{{service_name}}" منشورة الآن ومرئية للعملاء!'],
                'variables' => ['service_name', 'product_type'],
            ],
            [
                'event_key' => 'service.published',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Service published: {{service_name}}', 'ar' => 'تم نشر الخدمة: {{service_name}}'],
                'body' => ['en' => 'Great news! Your {{product_type}} service "{{service_name}}" has been approved and is now live on InstaParty. Customers can now discover and book it.', 'ar' => 'أخبار رائعة! تمت الموافقة على خدمة {{product_type}} "{{service_name}}" وهي منشورة الآن على InstaParty. يمكن للعملاء الآن اكتشافها وحجزها.'],
                'variables' => ['service_name', 'product_type'],
            ],
            [
                'event_key' => 'service.rejected',
                'channel' => NotificationChannel::Push,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Your service "{{service_name}}" was not approved. Tap to see the reason and make corrections.', 'ar' => 'لم يتم الموافقة على خدمتك "{{service_name}}". اضغط لمعرفة السبب وإجراء التصحيحات.'],
                'variables' => ['service_name', 'rejection_reason'],
            ],
            [
                'event_key' => 'service.rejected',
                'channel' => NotificationChannel::Email,
                'audience' => NotificationAudience::Vendor,
                'subject' => ['en' => 'Service not approved: {{service_name}}', 'ar' => 'لم تتم الموافقة على الخدمة: {{service_name}}'],
                'body' => ['en' => 'Your service "{{service_name}}" was reviewed and not approved. Reason: {{rejection_reason}}. Please address the issues and resubmit for review.', 'ar' => 'تمت مراجعة خدمتك "{{service_name}}" ولم تتم الموافقة عليها. السبب: {{rejection_reason}}. يرجى معالجة المشكلات وإعادة تقديمها للمراجعة.'],
                'variables' => ['service_name', 'rejection_reason'],
            ],

            // ── Admin inbox (SLA + auto-suspend) ──────────────────────────────

            [
                'event_key' => 'vendor.auto_suspended',
                'channel' => NotificationChannel::InApp,
                'audience' => NotificationAudience::Admin,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Vendor auto-suspended due to expired document. Review required.', 'ar' => 'تم إيقاف المورد تلقائياً بسبب مستند منتهي الصلاحية. يلزم المراجعة.'],
                'variables' => ['vendor_name', 'doc_type', 'expiry_date'],
            ],
            [
                'event_key' => 'vendor.document.uploaded',
                'channel' => NotificationChannel::InApp,
                'audience' => NotificationAudience::Admin,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'A vendor uploaded a new document awaiting review.', 'ar' => 'رفع مورد مستنداً جديداً في انتظار المراجعة.'],
                'variables' => ['vendor_name', 'doc_type'],
            ],
            [
                'event_key' => 'vendor.approval.sla_24h',
                'channel' => NotificationChannel::InApp,
                'audience' => NotificationAudience::Admin,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'Vendor application has been pending for 24+ hours without review. Action needed.', 'ar' => 'طلب المورد معلق لأكثر من 24 ساعة دون مراجعة. يلزم الإجراء.'],
                'variables' => [],
            ],
            [
                'event_key' => 'vendor.approval.sla_48h',
                'channel' => NotificationChannel::InApp,
                'audience' => NotificationAudience::Admin,
                'subject' => ['en' => null, 'ar' => null],
                'body' => ['en' => 'URGENT: Vendor application has been pending for 48+ hours. Immediate review required.', 'ar' => 'عاجل: طلب المورد معلق لأكثر من 48 ساعة. المراجعة الفورية مطلوبة.'],
                'variables' => [],
            ],
        ];
    }
}

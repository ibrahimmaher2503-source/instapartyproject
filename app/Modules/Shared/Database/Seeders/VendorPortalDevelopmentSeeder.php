<?php

declare(strict_types=1);

namespace App\Modules\Shared\Database\Seeders;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Communication\Domain\Enums\ChatFlagAction;
use App\Modules\Communication\Domain\Enums\ChatFlagType;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Domain\Models\NotificationPreference;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Enums\BusinessType;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Enums\DocumentType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class VendorPortalDevelopmentSeeder extends Seeder
{
    use SeedsDevelopmentData;

    public function run(): void
    {
        fake()->seed(2026050316);

        DB::transaction(function (): void {
            $admin = User::query()->where('email', 'admin@instaparty.local')->first();
            $cairo = Governorate::query()->where('code', 'EG-CAI')->first();
            $city = $cairo instanceof Governorate
                ? City::query()->where('governorate_id', $cairo->id)->orderBy('id')->first()
                : null;

            if ($admin instanceof User && $cairo instanceof Governorate && $city instanceof City) {
                $this->seedPortalStateVendors($admin, $cairo, $city);
            } else {
                $this->command?->warn('VendorPortalDevelopmentSeeder: admin/geography prerequisites missing; skipped portal status vendors.');
            }

            $this->seedVendorNotificationPreferences();
            $this->seedRestrictedChatThreads();
        });
    }

    private function seedPortalStateVendors(User $admin, Governorate $governorate, City $city): void
    {
        $rows = [
            [
                'name' => 'Mona Tarek',
                'email' => 'vendor.pending@instaparty.local',
                'phone' => '+201000000204',
                'slug' => 'pending-party-setups',
                'business_name' => ['en' => 'Pending Party Setups', 'ar' => 'تجهيزات حفلات قيد المراجعة'],
                'approval_status' => ApprovalStatus::Pending,
                'business_type' => BusinessType::Company,
                'document_status' => DocumentStatus::Pending,
                'rejection_reason' => null,
                'suspension_reason' => null,
            ],
            [
                'name' => 'Hany Magdy',
                'email' => 'vendor.changes@instaparty.local',
                'phone' => '+201000000205',
                'slug' => 'changes-requested-cakes',
                'business_name' => ['en' => 'Changes Requested Cakes', 'ar' => 'كيك يحتاج تعديلات'],
                'approval_status' => ApprovalStatus::ChangesRequested,
                'business_type' => BusinessType::Establishment,
                'document_status' => DocumentStatus::Rejected,
                'rejection_reason' => [
                    'en' => 'Please upload a clearer commercial register and complete bank details.',
                    'ar' => 'يرجى رفع سجل تجاري أوضح واستكمال بيانات البنك.',
                ],
                'suspension_reason' => null,
            ],
            [
                'name' => 'Yara Samir',
                'email' => 'vendor.suspended@instaparty.local',
                'phone' => '+201000000206',
                'slug' => 'suspended-balloon-studio',
                'business_name' => ['en' => 'Suspended Balloon Studio', 'ar' => 'استوديو بالونات موقوف'],
                'approval_status' => ApprovalStatus::Suspended,
                'business_type' => BusinessType::Individual,
                'document_status' => DocumentStatus::Approved,
                'rejection_reason' => null,
                'suspension_reason' => [
                    'en' => 'Account suspended for development testing.',
                    'ar' => 'تم إيقاف الحساب لاختبار بوابة البائع.',
                ],
            ],
        ];

        foreach ($rows as $row) {
            $user = $this->seedVendorUser($row['name'], $row['email'], $row['phone']);
            $profile = $this->seedVendorProfile($user, $admin, $governorate, $city, $row);
            $this->seedPortalDocument($profile, $admin, $row['document_status']);
        }
    }

    private function seedVendorUser(string $name, string $email, string $phone): User
    {
        /** @var User $user */
        $user = $this->updateOrCreateFactoryModel(
            User::factory()->phoneVerified()->make([
                'public_id' => $this->stablePublicId('user:'.$email),
                'name' => $name,
                'email' => $email,
                'phone_e164' => $phone,
                'preferred_locale' => 'ar',
                'timezone' => 'Africa/Cairo',
                'numeral_system' => 'western',
                'status' => 'active',
                'email_verified_at' => now(),
            ]),
            ['email' => $email],
        );

        $user->syncRoles(['vendor']);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function seedVendorProfile(
        User $user,
        User $admin,
        Governorate $governorate,
        City $city,
        array $row,
    ): VendorProfile {
        $status = $row['approval_status'];

        /** @var VendorProfile $profile */
        $profile = $this->updateOrCreateFactoryModel(
            VendorProfile::factory()->make([
                'public_id' => $this->stablePublicId('vendor-profile:'.$row['slug']),
                'user_id' => $user->id,
                'business_name' => $row['business_name'],
                'slug' => $row['slug'],
                'bio' => [
                    'en' => 'Vendor portal development account.',
                    'ar' => 'حساب تجريبي لاختبار بوابة البائع.',
                ],
                'business_type' => $row['business_type'],
                'commercial_register_no' => 'CR-PORTAL-'.substr($row['phone'], -3),
                'tax_id' => 'TAX-PORTAL-'.substr($row['phone'], -3),
                'national_id' => '29801011234567',
                'primary_governorate_id' => $governorate->id,
                'primary_city_id' => $city->id,
                'address_line' => ['en' => 'Vendor portal test address', 'ar' => 'عنوان تجريبي لبوابة البائع'],
                'approval_status' => $status->value,
                'approved_at' => $status === ApprovalStatus::Suspended ? now()->subDays(10) : null,
                'approved_by' => $status === ApprovalStatus::Suspended ? $admin->id : null,
                'rejected_at' => $status === ApprovalStatus::ChangesRequested ? now()->subDay() : null,
                'rejected_by' => $status === ApprovalStatus::ChangesRequested ? $admin->id : null,
                'suspended_at' => $status === ApprovalStatus::Suspended ? now()->subDay() : null,
                'suspended_by' => $status === ApprovalStatus::Suspended ? $admin->id : null,
                'rejection_reason' => $row['rejection_reason'],
                'suspension_reason' => $row['suspension_reason'],
                'bank_name' => 'Banque Misr',
                'bank_account_holder' => $row['name'],
                'bank_iban' => 'EG110019000500000000263180008',
                'bank_swift_bic' => 'BMISEGCX',
                'bank_branch' => 'Cairo Main',
            ]),
            ['slug' => $row['slug']],
        );

        return $profile;
    }

    private function seedPortalDocument(VendorProfile $profile, User $admin, DocumentStatus $status): void
    {
        $factory = VendorDocument::factory();

        if ($status === DocumentStatus::Approved) {
            $factory = $factory->approved();
        }

        if ($status === DocumentStatus::Rejected) {
            $factory = $factory->rejected();
        }

        $this->updateOrCreateFactoryModel(
            $factory->make([
                'public_id' => $this->stablePublicId('vendor-document:'.$profile->slug.':'.DocumentType::Cr->value),
                'vendor_profile_id' => $profile->id,
                'doc_type' => DocumentType::Cr,
                'file_path' => 'seed/vendor-documents/'.$profile->slug.'/cr.pdf',
                'file_name' => 'cr.pdf',
                'status' => $status,
                'reviewed_at' => $status === DocumentStatus::Pending ? null : now(),
                'reviewed_by' => $status === DocumentStatus::Pending ? null : $admin->id,
                'review_notes' => match ($status) {
                    DocumentStatus::Approved => ['en' => 'Approved portal demo document.', 'ar' => 'مستند تجريبي معتمد.'],
                    DocumentStatus::Rejected => ['en' => 'Document image is not clear.', 'ar' => 'صورة المستند غير واضحة.'],
                    DocumentStatus::Pending => null,
                },
            ]),
            ['vendor_profile_id' => $profile->id, 'doc_type' => DocumentType::Cr->value],
        );
    }

    private function seedVendorNotificationPreferences(): void
    {
        User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'vendor'))
            ->get()
            ->each(function (User $user): void {
                foreach ($this->vendorPreferenceRows() as $row) {
                    $this->updateOrCreateFactoryModel(
                        NotificationPreference::factory()->make([
                            'public_id' => $this->stablePublicId(
                                'notification-preference:'.$user->email.':'.$row['channel']->value.':'.$row['category']->value
                            ),
                            'user_id' => $user->id,
                            'channel' => $row['channel'],
                            'event_category' => $row['category'],
                            'is_enabled' => $row['enabled'],
                            'quiet_hours_start' => $row['quiet'] ? '23:00:00' : null,
                            'quiet_hours_end' => $row['quiet'] ? '08:00:00' : null,
                            'timezone' => 'Africa/Cairo',
                        ]),
                        ['user_id' => $user->id, 'channel' => $row['channel']->value, 'event_category' => $row['category']->value],
                    );
                }
            });
    }

    /**
     * @return array<int, array{channel: NotificationChannel, category: EventCategory, enabled: bool, quiet: bool}>
     */
    private function vendorPreferenceRows(): array
    {
        return [
            ['channel' => NotificationChannel::Email, 'category' => EventCategory::Booking, 'enabled' => true, 'quiet' => false],
            ['channel' => NotificationChannel::InApp, 'category' => EventCategory::Booking, 'enabled' => true, 'quiet' => false],
            ['channel' => NotificationChannel::Push, 'category' => EventCategory::Chat, 'enabled' => true, 'quiet' => true],
            ['channel' => NotificationChannel::Email, 'category' => EventCategory::Review, 'enabled' => true, 'quiet' => false],
            ['channel' => NotificationChannel::Whatsapp, 'category' => EventCategory::Payment, 'enabled' => false, 'quiet' => false],
        ];
    }

    private function seedRestrictedChatThreads(): void
    {
        BookingVendor::query()
            ->whereHas('booking', fn ($query) => $query->whereIn('reference_no', ['BK-DEV-1001', 'BK-DEV-1002', 'BK-DEV-1003', 'BK-DEV-1004', 'BK-DEV-1005']))
            ->with(['booking.customer', 'vendor.user'])
            ->get()
            ->each(function (BookingVendor $bookingVendor): void {
                $booking = $bookingVendor->booking;
                $vendor = $bookingVendor->vendor;

                if ($booking === null || $vendor === null || $booking->customer === null) {
                    return;
                }

                $isClosed = $bookingVendor->sub_status === VendorSubStatus::Completed;

                /** @var ChatThread $thread */
                $thread = $this->updateOrCreateFactoryModel(
                    ChatThread::factory()->make([
                        'public_id' => $this->stablePublicId('chat-thread:'.$booking->reference_no.':'.$vendor->slug),
                        'firestore_thread_id' => 'seed-thread-'.$this->stablePublicId('chat-firestore:'.$booking->reference_no.':'.$vendor->slug),
                        'customer_id' => $booking->customer_id,
                        'vendor_profile_id' => $vendor->id,
                        'booking_id' => $booking->id,
                        'status' => $isClosed ? 'closed' : 'open',
                        'locked_at' => $isClosed ? '2026-05-20 23:45:00' : null,
                        'frozen_at' => null,
                        'frozen_by' => null,
                    ]),
                    ['booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id],
                );

                $this->seedChatMessage($thread, $booking->customer_id, 'customer-intro', false, '2026-05-16 09:05:00');

                if ($vendor->user !== null) {
                    $this->seedChatMessage($thread, $vendor->user->id, 'vendor-reply', false, '2026-05-16 09:12:00');
                }

                if ($bookingVendor->sub_status === VendorSubStatus::Pending) {
                    $messageId = $this->seedChatMessage($thread, $booking->customer_id, 'blocked-phone', true, '2026-05-16 09:18:00');
                    $this->seedChatModerationFlag($messageId);
                }
            });
    }

    private function seedChatMessage(
        ChatThread $thread,
        int $senderId,
        string $messageKey,
        bool $flagged,
        string $createdAt,
    ): int {
        return $this->firstOrInsertRow(
            'chat_message_log',
            [
                'chat_thread_id' => $thread->id,
                'firestore_message_id' => 'seed-message-'.$this->stablePublicId('chat-message:'.$thread->public_id.':'.$messageKey),
            ],
            [
                'public_id' => $this->stablePublicId('chat-message-log:'.$thread->public_id.':'.$messageKey),
                'sender_id' => $senderId,
                'message_kind' => 'text',
                'detected_locale' => 'mixed',
                'flagged' => $flagged,
                'flag_reason' => $flagged ? 'phone_pattern' : null,
                'redacted' => false,
                'created_at' => $createdAt,
            ],
        );
    }

    private function seedChatModerationFlag(int $messageLogId): void
    {
        $this->firstOrInsertRow(
            'chat_moderation_flags',
            [
                'chat_message_log_id' => $messageLogId,
                'flag_type' => ChatFlagType::Phone->value,
            ],
            [
                'public_id' => $this->stablePublicId('chat-moderation-flag:'.$messageLogId.':phone'),
                'matched_pattern' => '+201001234567',
                'action_taken' => ChatFlagAction::Block->value,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'created_at' => '2026-05-16 09:18:01',
            ],
        );
    }
}

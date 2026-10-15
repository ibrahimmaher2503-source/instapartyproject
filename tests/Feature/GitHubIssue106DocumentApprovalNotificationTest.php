<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Jobs\DispatchNotificationJob;
use App\Modules\Communication\Application\Listeners\OnVendorDocumentApproved;
use App\Modules\Communication\Database\Seeders\GapNotificationTemplateSeeder;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Identity\Domain\Enums\DocumentType;
use App\Modules\Identity\Domain\Events\VendorDocumentApproved;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Pages\VendorDocumentsPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

it('stores a localized document approval in-app notification for the owning vendor', function (): void {
    Queue::fake();
    app(GapNotificationTemplateSeeder::class)->run();

    $vendorUser = User::factory()->create(['preferred_locale' => 'ar']);
    $otherVendor = User::factory()->create();
    $profile = VendorProfile::factory()->create(['user_id' => $vendorUser->id]);
    $document = VendorDocument::factory()->approved()->create([
        'vendor_profile_id' => $profile->id,
        'doc_type' => DocumentType::NationalId,
    ]);

    app(OnVendorDocumentApproved::class)->handle(new VendorDocumentApproved($document));

    $dispatch = NotificationDispatch::query()
        ->where('user_id', $vendorUser->id)
        ->where('channel', NotificationChannel::InApp->value)
        ->where('reference_type', 'vendor_document')
        ->where('reference_id', $document->id)
        ->firstOrFail();

    (new DispatchNotificationJob($dispatch->id))->handle(app());

    $notification = DB::table('notifications')->where('notifiable_id', $vendorUser->id)->first();
    $data = json_decode((string) $notification?->data, true, flags: JSON_THROW_ON_ERROR);

    expect($notification)->not->toBeNull()
        ->and($notification->notifiable_type)->toBe(User::class)
        ->and($data['title'])->toBe('تمت الموافقة على المستند')
        ->and($data['body'])->toBe('تمت الموافقة على مستندك.')
        ->and($data['action_url'])->toBe(VendorDocumentsPage::getUrl(panel: 'vendor'))
        ->and($data['reference_type'])->toBe('vendor_document')
        ->and($data['reference_id'])->toBe($document->id)
        ->and(DB::table('notifications')->where('notifiable_id', $otherVendor->id)->count())->toBe(0);
});

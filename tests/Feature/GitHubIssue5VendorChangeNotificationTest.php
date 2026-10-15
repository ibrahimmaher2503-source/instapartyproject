<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Application\Jobs\DispatchNotificationJob;
use App\Modules\Communication\Application\Listeners\DispatchVendorChangesRequestedNotificationListener;
use App\Modules\Communication\Database\Seeders\ChangeRequestNotificationTemplateSeeder;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Identity\Application\Actions\RequestVendorChangesAction;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Pages\VendorProfileCorrectionPage;
use App\Modules\Shared\Domain\Enums\ChangeRequestStatus;
use App\Modules\Shared\Domain\Events\ChangeRequestCreated;
use App\Modules\Shared\Domain\Models\ChangeRequest;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('dispatches vendor change notifications for the real vendor_profile request discriminator', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('manage_vendor_profile', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->unsetRelation('permissions');

    $vendorUser = User::factory()->create(['preferred_locale' => 'en']);
    $profile = VendorProfile::factory()->pending()->create(['user_id' => $vendorUser->id]);
    $request = app(RequestVendorChangesAction::class)->execute($profile, [[
        'field_path' => 'business_name.en',
        'requested_change_en' => 'Use the registered business name',
        'requested_change_ar' => 'استخدم الاسم التجاري المسجل',
    ]], $admin, 'github-issue-5-notification');

    $dispatched = [];
    $dispatcher = Mockery::mock(DispatchNotificationAction::class);
    $dispatcher->shouldReceive('execute')
        ->times(3)
        ->with(Mockery::on(function (DispatchNotificationDTO $dto) use (&$dispatched): bool {
            $dispatched[] = $dto;

            return true;
        }))
        ->andReturnNull();
    app()->instance(DispatchNotificationAction::class, $dispatcher);

    event(new ChangeRequestCreated($request));

    expect($request->fresh()->subject_type)->toBe('vendor_profile')
        ->and($request->fresh()->status)->toBe(ChangeRequestStatus::Open)
        ->and($dispatched)->toHaveCount(3)
        ->and(array_map(fn (DispatchNotificationDTO $dto): string => $dto->eventKey, $dispatched))
        ->toBe(['vendor.changes_requested', 'vendor.changes_requested', 'vendor.changes_requested'])
        ->and(array_map(fn (DispatchNotificationDTO $dto): NotificationChannel => $dto->channel, $dispatched))
        ->toBe([NotificationChannel::Push, NotificationChannel::Email, NotificationChannel::InApp])
        ->and($dispatched[0]->audience)->toBe(NotificationAudience::Vendor)
        ->and($dispatched[0]->eventCategory)->toBe(EventCategory::System)
        ->and($dispatched[0]->userId)->toBe($vendorUser->id)
        ->and($dispatched[0]->referenceType)->toBe('change_request')
        ->and($dispatched[0]->referenceId)->toBe($request->id)
        ->and($dispatched[2]->context['requested_changes'])->toContain('Use the registered business name')
        ->and($dispatched[2]->context['action_url'])->toBe(VendorProfileCorrectionPage::getUrl(panel: 'vendor'));
});

it('keeps legacy and current vendor profile morph aliases mapped to the same model', function (): void {
    $profile = VendorProfile::factory()->pending()->create();
    $legacyRequest = new ChangeRequest([
        'subject_type' => 'vendor',
        'subject_id' => $profile->id,
    ]);

    expect(Relation::getMorphedModel('vendor'))->toBe(VendorProfile::class)
        ->and(Relation::getMorphedModel('vendor_profile'))->toBe(VendorProfile::class)
        ->and($legacyRequest->subject)->toBeInstanceOf(VendorProfile::class)
        ->and($legacyRequest->subject_type)->toBe('vendor');
});

it('persists the requested vendor corrections in that vendor inbox through the database adapter', function (): void {
    Queue::fake();
    app(ChangeRequestNotificationTemplateSeeder::class)->run();

    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('manage_vendor_profile', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->unsetRelation('permissions');

    $vendorUser = User::factory()->create(['preferred_locale' => 'en']);
    $otherVendor = User::factory()->create();
    $profile = VendorProfile::factory()->pending()->create(['user_id' => $vendorUser->id]);
    $request = app(RequestVendorChangesAction::class)->execute($profile, [[
        'field_path' => 'business_name.en',
        'requested_change_en' => 'Use the registered business name',
        'requested_change_ar' => 'استخدم الاسم التجاري المسجل',
    ]], $admin, 'github-issue-5-inapp');

    app(DispatchVendorChangesRequestedNotificationListener::class)
        ->handle(new ChangeRequestCreated($request));

    $dispatch = NotificationDispatch::query()
        ->where('user_id', $vendorUser->id)
        ->where('channel', NotificationChannel::InApp->value)
        ->where('reference_type', 'change_request')
        ->where('reference_id', $request->id)
        ->firstOrFail();

    (new DispatchNotificationJob($dispatch->id))->handle(app());

    $notification = DB::table('notifications')->where('notifiable_id', $vendorUser->id)->first();
    $data = json_decode((string) $notification?->data, true, flags: JSON_THROW_ON_ERROR);

    expect($notification)->not->toBeNull()
        ->and($notification->notifiable_type)->toBe(User::class)
        ->and($data['body'])->toContain('business_name.en')
        ->and($data['body'])->toContain('Use the registered business name')
        ->and($data['action_url'])->toBe(VendorProfileCorrectionPage::getUrl(panel: 'vendor'))
        ->and($data['reference_type'])->toBe('change_request')
        ->and($data['reference_id'])->toBe($request->id)
        ->and(DB::table('notifications')->where('notifiable_id', $otherVendor->id)->count())->toBe(0);
});

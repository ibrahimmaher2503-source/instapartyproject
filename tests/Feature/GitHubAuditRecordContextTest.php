<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Application\Actions\UpdateAppSettingAction;
use App\Modules\Shared\Application\Services\SafeAuditChanges;
use App\Modules\Shared\Domain\Models\AppSetting;
use App\Modules\Shared\Filament\ActivityLog\ActivityLogPresenter;
use Filament\Facades\Filament;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

it('redacts nested and JSON encoded credentials including deleted setting context', function (): void {
    $masked = SafeAuditChanges::redact(['key' => 'gateway.api_key', 'old' => ['value' => 'OLD SECRET'], 'attributes' => ['value' => 'NEW SECRET'], 'nested' => '{"authorization":"BEARER SECRET","count":4}']);
    expect(json_encode($masked))->not->toContain('OLD SECRET', 'NEW SECRET', 'BEARER SECRET')
        ->and($masked['nested']['count'])->toBe(4);
    expect(json_encode(SafeAuditChanges::redact([
        'old' => '{"value":"OLD DELETED SECRET"}',
        'new' => '{"key":"gateway.api_key","value":"NEW DELETED SECRET"}',
    ])))->not->toContain('OLD DELETED SECRET', 'NEW DELETED SECRET');
});

it('records redacted before and after settings changes and links only accessible live subjects', function (): void {
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any_activitylog', 'view_activitylog', 'view_any_app::setting', 'view_app::setting', 'update_app::setting'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $setting = AppSetting::create(['key' => 'gateway.api_key', 'value' => 'OLD SECRET']);
    app(UpdateAppSettingAction::class)->execute($setting, ['value' => 'NEW SECRET']);
    $entry = Activity::where('description', 'settings_updated')->firstOrFail();
    expect(json_encode($entry->properties))->not->toContain('OLD SECRET', 'NEW SECRET');
    expect($entry->properties['old']['value'])->toBe(__('shared::operations.redacted'))
        ->and($entry->properties['attributes']['value'])->toBe(__('shared::operations.redacted'));
    expect(ActivityLogPresenter::recordUrl($entry))->toBeNull();
    expect(ActivityLogPresenter::safeChangesSummary($entry))->not->toContain('OLD SECRET', 'NEW SECRET');
    $this->actingAs(User::factory()->asCustomer()->create());
    expect(ActivityLogPresenter::recordUrl($entry))->toBeNull();
});

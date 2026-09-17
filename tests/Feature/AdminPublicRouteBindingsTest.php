<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Catalog\Filament\Resources\CategoryFieldSchemaResource;
use App\Modules\Communication\Domain\Models\Campaign;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use App\Modules\Communication\Filament\Resources\CampaignResource;
use App\Modules\Communication\Filament\Resources\NotificationTemplateResource;
use App\Modules\Identity\Domain\Models\Role;
use App\Modules\Shared\Domain\Models\AppSetting;
use App\Modules\Shared\Domain\Models\FeatureFlag;
use App\Modules\Shared\Filament\Resources\AppSettingResource;
use App\Modules\Shared\Filament\Resources\FeatureFlagResource;
use BezhanSalleh\FilamentShield\Resources\RoleResource;

it('resolves admin records by public or natural keys and rejects numeric ids', function () {
    $campaign = Campaign::factory()->create();
    $template = NotificationTemplate::factory()->create();
    $schema = CategoryFieldSchema::factory()->create();
    $setting = AppSetting::query()->create([
        'key' => 'marketplace.currency',
        'value' => ['code' => 'EGP'],
    ]);
    $flag = FeatureFlag::query()->create([
        'key' => 'catalog.quick_edit',
        'is_enabled' => false,
        'rollout_pct' => 0,
    ]);
    $role = Role::findOrCreate('support_lead', 'web');

    $bindings = [
        [CampaignResource::class, $campaign, $campaign->public_id],
        [NotificationTemplateResource::class, $template, $template->public_id],
        [CategoryFieldSchemaResource::class, $schema, $schema->public_id],
        [AppSettingResource::class, $setting, $setting->key],
        [FeatureFlagResource::class, $flag, $flag->key],
        [RoleResource::class, $role, $role->name],
    ];

    foreach ($bindings as [$resource, $record, $key]) {
        expect($resource::resolveRecordRouteBinding($key)?->is($record))->toBeTrue()
            ->and($resource::resolveRecordRouteBinding((string) $record->getKey()))->toBeNull();
    }
});

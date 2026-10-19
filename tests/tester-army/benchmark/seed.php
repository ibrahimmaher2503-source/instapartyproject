<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Catalog\Domain\Models\Service;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

// Included only after the parent seeder's disposable-database guard.
$foreignOwner = VendorProfile::factory()->approved()->create();
$foreignService = Service::factory()->rental()->changesRequested()->create([
    'vendor_profile_id' => $foreignOwner->id, 'base_price_minor' => 23456,
]);
$change = \App\Modules\Shared\Domain\Models\ChangeRequest::query()->create([
    'public_id' => (string) \Illuminate\Support\Str::ulid(), 'subject_type' => 'service',
    'subject_id' => $foreignService->id, 'requested_by_admin_id' => User::where('email', $accounts['admin']['username'])->value('id'),
    'status' => 'open', 'cycle_number' => 1,
]);
$change->items()->create([
    'public_id' => (string) \Illuminate\Support\Str::ulid(), 'field_path' => 'base_price_minor',
    'requested_change_en' => 'Correct the price.', 'requested_change_ar' => 'صحح السعر.', 'item_status' => 'pending',
]);
$vendor->update(['bank_iban' => 'EG000000000000000000000000001', 'bank_account_holder' => 'BENCHMARK ONLY HOLDER', 'bank_name' => 'BENCHMARK ONLY BANK']);
foreach (['view_vendor::profile', 'update_vendor::profile'] as $permission) {
    Role::findByName('super_admin', 'web')->givePermissionTo(Permission::findOrCreate($permission, 'web'));
}
file_put_contents($projectRoot.'/.e2e/system-audit/benchmark-fixture.json', json_encode([
    'database' => $database, 'foreign_service' => $foreignService->public_id,
    'foreign_owner_id' => $foreignOwner->id, 'attacker_vendor_id' => $vendor->id,
    'vendor_id' => $vendor->id, 'admin_edit_url' => \App\Modules\Identity\Filament\Resources\VendorProfileResource::getUrl('edit', ['record' => $vendor], panel: 'admin', isAbsolute: false),
], JSON_THROW_ON_ERROR));

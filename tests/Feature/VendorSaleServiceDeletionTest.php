<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\DeleteVendorDraftSaleServiceAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\PendingReviewState;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource\Pages\ListVendorSaleServices;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
});

it('lets a vendor delete only their own draft sale service', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $otherVendor = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()->forType(ProductType::Sale)->create([
        'vendor_profile_id' => $vendor->id,
    ]);
    $vendor->user->givePermissionTo(Permission::findOrCreate('service.delete.sale.own', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $draft = Service::factory()->sale()->create(['vendor_profile_id' => $vendor->id]);
    $foreignDraft = Service::factory()->sale()->create(['vendor_profile_id' => $otherVendor->id]);
    $published = Service::factory()->sale()->published()->create(['vendor_profile_id' => $vendor->id]);
    $submitted = Service::factory()->sale()->pendingReview()->create(['vendor_profile_id' => $vendor->id]);

    $this->actingAs($vendor->user, 'vendor');

    Livewire::test(ListVendorSaleServices::class)
        ->assertTableActionVisible('delete', $draft)
        ->assertTableActionHidden('delete', $published)
        ->assertTableActionHidden('delete', $submitted)
        ->callTableAction('delete', $draft)
        ->assertNotified();

    expect(Service::withTrashed()->whereKey($draft->id)->value('deleted_at'))->not->toBeNull();

    expect(fn () => app(DeleteVendorDraftSaleServiceAction::class)->execute($foreignDraft, $vendor))
        ->toThrow(ValidationException::class);
    expect(fn () => app(DeleteVendorDraftSaleServiceAction::class)->execute($published, $vendor))
        ->toThrow(ValidationException::class);
    expect(fn () => app(DeleteVendorDraftSaleServiceAction::class)->execute($submitted, $vendor))
        ->toThrow(ValidationException::class);

    expect($foreignDraft->fresh())->not->toBeNull()
        ->and($published->fresh()->status)->toBeInstanceOf(PublishedState::class)
        ->and($submitted->fresh()->status)->toBeInstanceOf(PendingReviewState::class);
});

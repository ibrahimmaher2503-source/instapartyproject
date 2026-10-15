<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource\Pages\ReviewVendorApplication;
use App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('reviews the correct document and approves it from the vendor application only with review permission', function (string $locale, bool $canReview): void {
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->create();
    $permissions = ['view_any_vendor::profile', 'view_vendor::profile', 'view_vendor::document::filament'];
    if ($canReview) {
        $permissions[] = 'review_vendor_documents';
    }
    foreach ($permissions as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($admin);
    $vendor = VendorProfile::factory()->pending()->create();
    $document = VendorDocument::factory()->create(['vendor_profile_id' => $vendor->id, 'status' => DocumentStatus::Pending]);
    $other = VendorDocument::factory()->create(['status' => DocumentStatus::Pending]);
    $page = Livewire::test(ReviewVendorApplication::class, ['record' => $vendor->getRouteKey()])
        ->assertSee(VendorDocumentFilamentResource::getUrl('view', ['record' => $document]));

    if ($canReview) {
        $page->assertInfolistActionVisible('documents.0.file_name', 'approveDocument')
            ->callInfolistAction('documents.0.file_name', 'approveDocument')
            ->assertHasNoInfolistActionErrors()
            ->assertInfolistActionHidden('documents.0.file_name', 'approveDocument');
        expect($document->fresh()->status)->toBe(DocumentStatus::Approved)
            ->and($document->fresh()->reviewed_by)->toBe($admin->id);
    } else {
        $page->assertInfolistActionHidden('documents.0.file_name', 'approveDocument');
        expect($document->fresh()->status)->toBe(DocumentStatus::Pending);
    }
    expect($other->fresh()->status)->toBe(DocumentStatus::Pending);
})->with(['en', 'ar'])->with([true, false]);

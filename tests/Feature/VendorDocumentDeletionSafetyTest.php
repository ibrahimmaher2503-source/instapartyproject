<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Actions\DeleteVendorDocumentAction;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Pages\VendorDocumentsPage;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('lets the owner delete unapproved documents through the real table action', function (DocumentStatus $status): void {
    Storage::fake('local');
    config(['filesystems.default' => 'local', 'filesystems.disks.s3-private.key' => null]);
    $profile = VendorProfile::factory()->create();
    $document = VendorDocument::factory()->create(['vendor_profile_id' => $profile->id, 'status' => $status->value]);
    Storage::disk('local')->put($document->file_path, '%PDF-1.7');
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($profile->user, 'vendor');

    Livewire::test(VendorDocumentsPage::class)->assertTableActionVisible('delete', $document)
        ->callTableAction('delete', $document)->assertNotified();
    expect(VendorDocument::query()->whereKey($document->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($document->file_path);
})->with([DocumentStatus::Pending, DocumentStatus::Rejected]);

it('preserves an approved document even when the caller has a stale pending snapshot', function (): void {
    Storage::fake('local');
    config(['filesystems.default' => 'local', 'filesystems.disks.s3-private.key' => null]);
    $profile = VendorProfile::factory()->create();
    $document = VendorDocument::factory()->create(['vendor_profile_id' => $profile->id, 'status' => DocumentStatus::Pending->value]);
    Storage::disk('local')->put($document->file_path, '%PDF-1.7');
    VendorDocument::query()->whereKey($document->id)->update(['status' => DocumentStatus::Approved->value]);

    expect(fn () => app(DeleteVendorDocumentAction::class)->execute($profile, $document))->toThrow(ValidationException::class);
    expect($document->fresh()->status)->toBe(DocumentStatus::Approved);
    Storage::disk('local')->assertExists($document->file_path);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($profile->user, 'vendor');
    Livewire::test(VendorDocumentsPage::class)->assertTableActionHidden('delete', $document->fresh());
});

it('does not delete another vendor document or file', function (): void {
    Storage::fake('local');
    $profile = VendorProfile::factory()->create();
    $document = VendorDocument::factory()->create(['status' => DocumentStatus::Pending->value]);
    Storage::disk('local')->put($document->file_path, '%PDF-1.7');
    expect(fn () => app(DeleteVendorDocumentAction::class)->execute($profile, $document))->toThrow(ValidationException::class);
    expect($document->fresh())->not->toBeNull();
    Storage::disk('local')->assertExists($document->file_path);
});

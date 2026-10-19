<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Actions\ReuploadVendorDocumentAction;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Pages\VendorDocumentsPage;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Support\VendorDocumentTestFiles;

it('lets the owner replace an expired approved document while retaining its history', function (): void {
    Storage::fake('local');
    config(['filesystems.default' => 'local', 'filesystems.disks.s3-private.key' => null]);
    $profile = VendorProfile::factory()->create();
    $document = VendorDocument::factory()->create([
        'vendor_profile_id' => $profile->id,
        'status' => DocumentStatus::Approved->value,
        'expires_at' => today()->subDay(),
    ]);
    Storage::disk('local')->put($document->file_path, '%PDF-1.7');
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($profile->user, 'vendor');

    Livewire::test(VendorDocumentsPage::class)
        ->assertTableActionVisible('reupload', $document)
        ->callTableAction('reupload', $document, data: [
            'file' => UploadedFile::fake()->createWithContent('replacement.pdf', VendorDocumentTestFiles::minimalPdf()),
        ])
        ->assertNotified();

    $replacement = VendorDocument::query()->where('id', '!=', $document->id)->firstOrFail();

    expect($document->fresh()->status)->toBe(DocumentStatus::Approved)
        ->and($document->fresh()->expires_at?->toDateString())->toBe(today()->subDay()->toDateString())
        ->and($replacement->status)->toBe(DocumentStatus::Pending)
        ->and($replacement->doc_type_key)->toBe($document->doc_type_key)
        ->and($replacement->expires_at)->toBeNull()
        ->and($replacement->file_name)->toBe('replacement.pdf');
    Storage::disk('local')->assertExists($document->file_path);
    Storage::disk('local')->assertExists($replacement->file_path);
});

it('does not let an owner replace an approved document before it expires', function (): void {
    Storage::fake('local');
    config(['filesystems.default' => 'local', 'filesystems.disks.s3-private.key' => null]);
    $profile = VendorProfile::factory()->create();
    $document = VendorDocument::factory()->create([
        'vendor_profile_id' => $profile->id,
        'status' => DocumentStatus::Approved->value,
        'expires_at' => today()->addDay(),
    ]);
    Storage::disk('local')->put($document->file_path, '%PDF-1.7');

    expect(fn () => app(ReuploadVendorDocumentAction::class)->execute(
        $document,
        UploadedFile::fake()->createWithContent('replacement.pdf', VendorDocumentTestFiles::minimalPdf()),
        $profile->user,
    ))->toThrow(ConflictHttpException::class);

    expect($profile->documents()->count())->toBe(1);
    Storage::disk('local')->assertExists($document->file_path);
    expect(Storage::disk('local')->allFiles())->toHaveCount(1);
});

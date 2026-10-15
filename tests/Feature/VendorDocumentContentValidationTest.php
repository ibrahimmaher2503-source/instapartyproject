<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Actions\ReuploadVendorDocumentAction;
use App\Modules\Identity\Application\Actions\UploadVendorDocumentAction;
use App\Modules\Identity\Domain\Enums\DocumentType;
use App\Modules\Identity\Domain\Events\VendorDocumentUploaded;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Pages\VendorDocumentsPage;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('rejects text content disguised as a PDF before storing or creating a document', function (bool $reupload): void {
    Storage::fake('local');
    config(['filesystems.default' => 'local', 'filesystems.disks.s3-private.key' => null]);
    Event::fake([VendorDocumentUploaded::class]);
    $profile = VendorProfile::factory()->create();
    $original = $reupload ? VendorDocument::factory()->rejected()->create(['vendor_profile_id' => $profile->id]) : null;
    $file = UploadedFile::fake()->createWithContent('qa-fake.pdf', 'This is plain text, not a PDF document.');
    // The client MIME header is irrelevant; inspect the actual bytes on disk.
    $upload = new UploadedFile($file->getPathname(), 'qa-fake.pdf', 'application/pdf', null, true);
    expect($upload->getMimeType())->toBe('text/plain');

    expect(fn () => $reupload
        ? app(ReuploadVendorDocumentAction::class)->execute($original, $upload, $profile->user)
        : app(UploadVendorDocumentAction::class)->execute($profile, $upload, DocumentType::NationalId))
        ->toThrow(ValidationException::class);
    expect($profile->documents()->count())->toBe($reupload ? 1 : 0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
    Event::assertNotDispatched(VendorDocumentUploaded::class);
})->with([false, true]);

it('reports content rejection in the actual upload modal when the client claims PDF MIME', function (bool $reupload): void {
    Storage::fake('tmp-for-tests');
    Storage::fake('local');
    config(['filesystems.default' => 'local', 'filesystems.disks.s3-private.key' => null]);
    Event::fake([VendorDocumentUploaded::class]);
    $profile = VendorProfile::factory()->create();
    $original = $reupload ? VendorDocument::factory()->rejected()->create(['vendor_profile_id' => $profile->id]) : null;
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($profile->user, 'vendor');
    $file = UploadedFile::fake()->createWithContent('qa-fake.pdf', 'This is plain text, not a PDF document.')
        ->mimeType('application/pdf');

    $page = Livewire::test(VendorDocumentsPage::class);
    if ($reupload) {
        $page->callTableAction('reupload', $original, data: ['file' => $file]);
    } else {
        $page->callAction('upload', data: ['doc_type' => DocumentType::NationalId->value, 'file' => $file]);
    }
    $page->assertNotified(Notification::make()->title(__('identity::identity.validation.file_mime_invalid'))->danger());
    expect($profile->documents()->count())->toBe($reupload ? 1 : 0)->and(Storage::disk('local')->allFiles())->toBe([]);
    Event::assertNotDispatched(VendorDocumentUploaded::class);
})->with([false, true]);

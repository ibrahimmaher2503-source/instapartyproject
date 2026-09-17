<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Enums\DocumentType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Pages\VendorDocumentsPage;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;

it('stores a vendor document through the Livewire temporary upload pipeline', function (): void {
    Storage::fake('tmp-for-tests');
    Storage::fake('local');
    Storage::fake('public');
    config([
        'filesystems.default' => 'local',
        'filesystems.disks.s3-private.key' => null,
    ]);
    Notification::fake();

    $profile = VendorProfile::factory()->create();
    $user = User::query()->whereKey($profile->user_id)->firstOrFail();

    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    Auth::guard('vendor')->login($user);
    Auth::shouldUse('vendor');

    Livewire::test(VendorDocumentsPage::class)
        ->callAction('upload', data: [
            'doc_type' => DocumentType::NationalId->value,
            'file' => UploadedFile::fake()->create('national-id.pdf', 40, 'application/pdf'),
        ])
        ->assertHasNoActionErrors();

    $document = VendorDocument::query()
        ->where('vendor_profile_id', $profile->id)
        ->where('doc_type', DocumentType::NationalId->value)
        ->firstOrFail();

    expect($document->status)->toBe(DocumentStatus::Pending)
        ->and($document->file_name)->toBe('national-id.pdf')
        ->and($document->file_path)->toStartWith("vendors/{$profile->id}/documents/")
        ->and(Storage::disk('local')->exists($document->file_path))->toBeTrue()
        ->and(Storage::disk('public')->exists($document->file_path))->toBeFalse()
        ->and(config('filesystems.disks.tmp-for-tests.driver'))->toBe('local')
        ->and(config('filesystems.disks.tmp-for-tests.visibility'))->toBe('private');
});

it('keeps the temporary upload in the mounted action state before submit', function (): void {
    Storage::fake('tmp-for-tests');
    Storage::fake('local');
    Storage::fake('public');
    config([
        'filesystems.default' => 'local',
        'filesystems.disks.s3-private.key' => null,
    ]);
    Notification::fake();

    $profile = VendorProfile::factory()->create();
    $user = User::query()->whereKey($profile->user_id)->firstOrFail();

    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    Auth::guard('vendor')->login($user);
    Auth::shouldUse('vendor');

    $component = Livewire::test(VendorDocumentsPage::class)
        ->mountAction('upload')
        ->set('mountedActionsData.0.doc_type', DocumentType::NationalId->value)
        ->upload(
            'mountedActionsData.0.file',
            [UploadedFile::fake()->create('national-id.pdf', 40, 'application/pdf')],
        );

    expect($component->instance()->mountedActionsData[0]['file'] ?? null)
        ->toBeArray();

    $component->callMountedAction()->assertHasNoActionErrors();

    expect(VendorDocument::query()
        ->where('vendor_profile_id', $profile->id)
        ->where('doc_type', DocumentType::NationalId->value)
        ->exists())->toBeTrue();
});

it('accepts the browser file key upload before the mounted action submit', function (): void {
    Storage::fake('tmp-for-tests');
    Storage::fake('local');
    Storage::fake('public');
    config([
        'filesystems.default' => 'local',
        'filesystems.disks.s3-private.key' => null,
    ]);
    Notification::fake();

    $profile = VendorProfile::factory()->create();
    $user = User::query()->whereKey($profile->user_id)->firstOrFail();

    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    Auth::guard('vendor')->login($user);
    Auth::shouldUse('vendor');

    $component = Livewire::test(VendorDocumentsPage::class)
        ->mountAction('upload')
        ->set('mountedActionsData.0.doc_type', DocumentType::NationalId->value)
        ->upload(
            'mountedActionsData.0.file.8d8a9f15-39af-4a66-9c1b-2f2f4db0d7b1',
            [UploadedFile::fake()->create('national-id.pdf', 40, 'application/pdf')],
        );

    $fileState = $component->instance()->mountedActionsData[0]['file'] ?? null;

    expect($fileState)->toBeArray()
        ->and(array_values($fileState)[0] ?? null)
        ->toBeInstanceOf(TemporaryUploadedFile::class);

    $component->callMountedAction()->assertHasNoActionErrors();

    expect(VendorDocument::query()
        ->where('vendor_profile_id', $profile->id)
        ->where('doc_type', DocumentType::NationalId->value)
        ->exists())->toBeTrue();
});

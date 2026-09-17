<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Actions\ApproveVendorDocumentAction;
use App\Modules\Identity\Application\Actions\GenerateDocumentSignedUrlAction;
use App\Modules\Identity\Application\Actions\RejectVendorDocumentAction;
use App\Modules\Identity\Application\Actions\ReuploadVendorDocumentAction;
use App\Modules\Identity\Application\Actions\SetDocumentExpiryAction;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Events\VendorDocumentApproved;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

function vendorDocumentReviewer(): User
{
    $permission = Permission::findOrCreate('review_vendor_documents', 'web');
    $reviewer = User::factory()->create();
    $reviewer->givePermissionTo($permission);

    return $reviewer;
}

it('requires document rejection reasons and Action authorization', function (): void {
    $document = VendorDocument::factory()->create();
    $reviewer = vendorDocumentReviewer();
    $unauthorized = User::factory()->create();

    expect(fn () => app(RejectVendorDocumentAction::class)->execute($document, [], $reviewer))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(ApproveVendorDocumentAction::class)->execute($document, $unauthorized))
        ->toThrow(HttpException::class)
        ->and(fn () => app(GenerateDocumentSignedUrlAction::class)->execute($document, $unauthorized))
        ->toThrow(HttpException::class);
});

it('rejects missing and unsafe document paths before generating access URLs', function (): void {
    Storage::fake('local');
    config(['filesystems.default' => 'local', 'filesystems.disks.s3-private.key' => null]);
    $reviewer = vendorDocumentReviewer();
    $missing = VendorDocument::factory()->create(['file_path' => 'vendors/missing.pdf']);
    $unsafe = VendorDocument::factory()->create(['file_path' => '../../.env']);
    $windowsUnsafe = VendorDocument::factory()->create(['file_path' => 'vendors\\..\\secrets.pdf']);
    $absolute = VendorDocument::factory()->create(['file_path' => '/etc/passwd']);

    expect(fn () => app(GenerateDocumentSignedUrlAction::class)->execute($missing, $reviewer))
        ->toThrow(HttpException::class, '', 404)
        ->and(fn () => app(GenerateDocumentSignedUrlAction::class)->execute($unsafe, $reviewer))
        ->toThrow(HttpException::class, '', 404)
        ->and(fn () => app(GenerateDocumentSignedUrlAction::class)->execute($windowsUnsafe, $reviewer))
        ->toThrow(HttpException::class, '', 404)
        ->and(fn () => app(GenerateDocumentSignedUrlAction::class)->execute($absolute, $reviewer))
        ->toThrow(HttpException::class, '', 404);
});

it('generates a temporary URL for an authorized owner without exposing the storage path', function (): void {
    Storage::fake('local');
    config(['filesystems.default' => 'local', 'filesystems.disks.s3-private.key' => null]);
    $owner = User::factory()->create();
    $profile = VendorProfile::factory()->create(['user_id' => $owner->id]);
    $path = 'documents/'.(string) Str::ulid().'.pdf';
    Storage::disk('local')->put($path, '%PDF-1.7');
    $document = VendorDocument::factory()->create([
        'vendor_profile_id' => $profile->id,
        'file_path' => $path,
        'file_name' => 'بطاقة-هوية.pdf',
    ]);

    $url = app(GenerateDocumentSignedUrlAction::class)->execute($document, $owner);

    expect($url)->toBeString()->not->toBe('')
        ->and(DB::table('audit_logs')
            ->where('auditable_type', VendorDocument::class)
            ->where('auditable_id', $document->id)
            ->where('action', 'signed_url_generated')
            ->count())->toBe(1);
});

it('updates expiry through the review action and records an audit event', function (): void {
    $document = VendorDocument::factory()->create([
        'expires_at' => today()->addDay(),
        'is_critical' => false,
    ]);
    $reviewer = vendorDocumentReviewer();
    $expiresAt = now()->addDays(30);

    $updated = app(SetDocumentExpiryAction::class)->execute($document, $expiresAt, true, $reviewer);

    expect($updated->refresh()->expires_at?->toDateString())->toBe($expiresAt->toDateString())
        ->and($updated->is_critical)->toBeTrue()
        ->and(DB::table('activity_log')
            ->where('subject_type', VendorDocument::class)
            ->where('subject_id', $document->id)
            ->where('description', 'updated_vendor_document_expiry')
            ->count())->toBe(1);

    expect(fn () => app(SetDocumentExpiryAction::class)->execute(
        $document,
        now()->subDay(),
        false,
        $reviewer,
    ))->toThrow(InvalidArgumentException::class);
});

it('does not serialize a private document storage path', function (): void {
    $document = VendorDocument::factory()->create(['file_path' => 'documents/private.pdf']);

    expect($document->attributesToArray())->not->toHaveKey('file_path');
});

it('approves a pending document exactly once and rejects competing decisions', function (): void {
    Event::fake([VendorDocumentApproved::class]);
    $document = VendorDocument::factory()->create();
    $reviewer = vendorDocumentReviewer();

    $approved = app(ApproveVendorDocumentAction::class)->execute($document, $reviewer);
    $again = app(ApproveVendorDocumentAction::class)->execute($document, $reviewer);

    expect($approved->status)->toBe(DocumentStatus::Approved)
        ->and($again->id)->toBe($approved->id)
        ->and($approved->reviewed_by)->toBe($reviewer->id)
        ->and(fn () => app(RejectVendorDocumentAction::class)->execute(
            $document,
            ['en' => 'Competing rejection'],
            $reviewer,
        ))->toThrow(ConflictHttpException::class);

    expect(DB::table('activity_log')
        ->where('subject_type', VendorDocument::class)
        ->where('subject_id', $document->id)
        ->where('description', 'approved_vendor_document')
        ->count())->toBe(1);
});

it('preserves rejected document history and creates one pending re-upload', function (): void {
    Storage::fake('local');
    config(['filesystems.default' => 'local', 'filesystems.disks.s3-private.key' => null]);
    $owner = User::factory()->create();
    $profile = VendorProfile::factory()->create(['user_id' => $owner->id]);
    $rejected = VendorDocument::factory()->rejected()->create([
        'vendor_profile_id' => $profile->id,
        'doc_type' => 'national_id',
        'expires_at' => today()->addMonth(),
        'is_critical' => true,
    ]);

    $replacement = app(ReuploadVendorDocumentAction::class)->execute(
        $rejected,
        UploadedFile::fake()->create('national-id.pdf', 40, 'application/pdf'),
        $owner,
    );

    expect($rejected->refresh()->status)->toBe(DocumentStatus::Rejected)
        ->and($replacement->status)->toBe(DocumentStatus::Pending)
        ->and($replacement->doc_type->value)->toBe('national_id')
        ->and($replacement->is_critical)->toBeTrue();

    expect(fn () => app(ReuploadVendorDocumentAction::class)->execute(
        $rejected,
        UploadedFile::fake()->create('again.pdf', 40, 'application/pdf'),
        $owner,
    ))->toThrow(ConflictHttpException::class);
});

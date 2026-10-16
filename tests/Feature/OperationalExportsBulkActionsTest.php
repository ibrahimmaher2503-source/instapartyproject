<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource\Pages\ListVendorDocuments;
use App\Modules\Identity\Filament\Resources\VendorProfileResource\Pages\ListVendorProfiles;
use App\Modules\Support\Domain\Enums\SupportTicketStatus;
use App\Modules\Support\Domain\Models\SupportTicket;
use App\Modules\Support\Filament\Resources\SupportTicketResource\Pages\ListSupportTickets;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function grantOperationalPermissions(User $user, array $permissions): void
{
    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

it('gates vendor and document exports and uses scoped document review actions', function (): void {
    $reviewPermission = Permission::findOrCreate('review_vendor_documents', 'web');
    Role::findByName('admin', 'web')->revokePermissionTo($reviewPermission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $admin = User::factory()->asAdmin()->create();
    grantOperationalPermissions($admin, [
        'view_any_vendor::profile', 'view_vendor::profile',
        'view_any_vendor::document::filament',
    ]);
    $profile = VendorProfile::factory()->create();
    $document = VendorDocument::factory()->create(['vendor_profile_id' => $profile->id]);
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ListVendorProfiles::class)->assertDontSee(__('shared::operations.export'));
    Livewire::test(ListVendorDocuments::class)->assertDontSee(__('shared::operations.export'));

    grantOperationalPermissions($admin, ['export_operational_records', 'view_vendor::document::filament']);
    Livewire::test(ListVendorProfiles::class)->assertSee(__('shared::operations.export'));
    Livewire::test(ListVendorDocuments::class)->assertSee(__('shared::operations.export'));

    Livewire::test(ListVendorDocuments::class)
        ->assertTableBulkActionHidden('approveSelected');
    expect($document->fresh()->status)->toBe(DocumentStatus::Pending);

    grantOperationalPermissions($admin, ['review_vendor_documents']);
    Livewire::test(ListVendorDocuments::class)
        ->assertTableBulkActionVisible('approveSelected')
        ->callTableBulkAction('approveSelected', [$document]);
    expect($document->fresh()->status)->toBe(DocumentStatus::Approved)
        ->and($document->fresh()->reviewed_by)->toBe($admin->id);

    $rejectable = VendorDocument::factory()->create(['vendor_profile_id' => $profile->id]);
    Livewire::test(ListVendorDocuments::class)
        ->callTableBulkAction('rejectSelected', [$rejectable], ['review_notes_en' => 'Does not match requirements']);
    expect($rejectable->fresh()->status)->toBe(DocumentStatus::Rejected)
        ->and($rejectable->fresh()->reviewed_by)->toBe($admin->id)
        ->and($rejectable->fresh()->getTranslation('review_notes', 'en', false))->toBe('Does not match requirements');
});

it('bulk assigns and resolves support tickets through the authorized status action', function (): void {
    $admin = User::factory()->asAdmin()->create();
    grantOperationalPermissions($admin, ['view_any_support::ticket', 'view_support::ticket', 'update_support::ticket']);
    $open = SupportTicket::query()->create([
        'public_id' => (string) Str::ulid(), 'subject' => 'Assign me', 'body' => 'Ticket details', 'status' => SupportTicketStatus::Open,
    ]);
    $inProgress = SupportTicket::query()->create([
        'public_id' => (string) Str::ulid(), 'subject' => 'Resolve me', 'body' => 'Ticket details', 'status' => SupportTicketStatus::InProgress,
    ]);
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ListSupportTickets::class)
        ->assertTableBulkActionVisible('markSelectedInProgress')
        ->callTableBulkAction('markSelectedInProgress', [$open]);
    expect($open->fresh()->status)->toBe(SupportTicketStatus::InProgress)
        ->and($open->fresh()->assigned_to)->toBe($admin->id);

    Livewire::test(ListSupportTickets::class)
        ->assertTableBulkActionVisible('resolveSelected')
        ->callTableBulkAction('resolveSelected', [$inProgress]);
    expect($inProgress->fresh()->status)->toBe(SupportTicketStatus::Resolved);

    $viewer = User::factory()->asAdmin()->create();
    grantOperationalPermissions($viewer, ['view_any_support::ticket', 'view_support::ticket']);
    $this->actingAs($viewer);
    Livewire::test(ListSupportTickets::class)->assertTableBulkActionHidden('resolveSelected');
    expect($inProgress->fresh()->status)->toBe(SupportTicketStatus::Resolved);
});

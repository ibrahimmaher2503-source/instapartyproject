<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Application\Actions\AddInternalRecordNoteAction;
use App\Modules\Shared\Application\Actions\SyncInternalRecordTagsAction;
use App\Modules\Shared\Domain\Exceptions\AppendOnlyViolationException;
use App\Modules\Shared\Domain\Models\InternalRecordNote;
use App\Modules\Shared\Filament\Resources\RelationManagers\InternalRecordNotesRelationManager;
use App\Modules\Support\Domain\Models\SupportTicket;
use App\Modules\Support\Filament\Resources\SupportTicketResource;
use App\Modules\Support\Filament\Resources\SupportTicketResource\Pages\ListSupportTickets;
use App\Modules\Support\Filament\Resources\SupportTicketResource\Pages\ViewSupportTicket;
use App\Modules\TrustSafety\Domain\Models\Report;
use App\Modules\TrustSafety\Filament\Resources\ReportResource;
use App\Modules\TrustSafety\Filament\Resources\ReportResource\Pages\ListReports;
use App\Modules\TrustSafety\Filament\Resources\ReportResource\Pages\ViewReport;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Tags\Tag;

function sharedAdminNoteActor(array $permissions): User
{
    $actor = User::factory()->create();
    foreach ($permissions as $permission) {
        $actor->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $actor;
}

it('adds append-only internal notes to support tickets and reports with record permissions', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $actor = sharedAdminNoteActor([
        'view_support::ticket', 'update_support::ticket',
        'view_report', 'update_report',
    ]);
    $ticket = SupportTicket::create(['subject' => 'Question', 'body' => 'Need help', 'email' => 'guest@example.test']);
    $reporter = User::factory()->create();
    $report = Report::query()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => User::class,
        'reportable_id' => $reporter->id,
        'reason' => 'other',
        'status' => 'open',
    ]);

    $action = app(AddInternalRecordNoteAction::class);
    $ticketNote = $action->execute($ticket, $actor, 'contact', 'Called the customer.');
    $reportNote = $action->execute($report, $actor, 'note', 'Review the submitted evidence.');

    expect($ticketNote->fresh()->subject->is($ticket))->toBeTrue()
        ->and($reportNote->fresh()->subject->is($report))->toBeTrue()
        ->and($ticketNote->fresh()->author_id)->toBe($actor->id)
        ->and(fn () => $ticketNote->update(['body' => 'Changed']))->toThrow(AppendOnlyViolationException::class)
        ->and(fn () => $ticketNote->delete())->toThrow(AppendOnlyViolationException::class);
});

it('requires update permission before adding a note and exposes notes only from admin record pages', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $actor = sharedAdminNoteActor(['view_support::ticket']);
    $ticket = SupportTicket::create(['subject' => 'Question', 'body' => 'Need help', 'email' => 'guest@example.test']);

    expect(fn () => app(AddInternalRecordNoteAction::class)->execute($ticket, $actor, 'note', 'No update access.'))
        ->toThrow(AuthorizationException::class)
        ->and(InternalRecordNote::query()->count())->toBe(0);

    expect(SupportTicketResource::getRelations())->toContain(InternalRecordNotesRelationManager::class)
        ->and(ReportResource::getRelations())->toContain(InternalRecordNotesRelationManager::class);

    $this->actingAs(sharedAdminNoteActor(['view_any_support::ticket', 'view_support::ticket', 'update_support::ticket']));
    Livewire::test(InternalRecordNotesRelationManager::class, [
        'ownerRecord' => $ticket,
        'pageClass' => ViewSupportTicket::class,
    ])->assertSuccessful()->assertTableActionExists('addInternalNote');

    $reporter = User::factory()->create();
    $report = Report::query()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => User::class,
        'reportable_id' => $reporter->id,
        'reason' => 'other',
        'status' => 'open',
    ]);
    $this->actingAs(sharedAdminNoteActor(['view_any_report', 'view_report', 'update_report']));
    Livewire::test(InternalRecordNotesRelationManager::class, [
        'ownerRecord' => $report,
        'pageClass' => ViewReport::class,
    ])->assertSuccessful()->assertTableActionExists('addInternalNote');
});

it('shows configured age indicators and starts support and report lists with oldest work', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $actor = sharedAdminNoteActor([
        'view_any_support::ticket', 'view_support::ticket',
        'view_any_report', 'view_report',
    ]);
    $this->actingAs($actor);

    $ticketPage = Livewire::test(SupportTicketResource::getPages()['index']->getPage())
        ->assertTableColumnExists('queue_age')
        ->assertTableFilterExists('overdue');
    $reportPage = Livewire::test(ReportResource::getPages()['index']->getPage())
        ->assertTableColumnExists('queue_age')
        ->assertTableFilterExists('overdue');

    expect($ticketPage->instance()->getTable()->getDefaultSortColumn())->toBe('created_at')
        ->and($reportPage->instance()->getTable()->getDefaultSortColumn())->toBe('created_at');
});

it('runs scoped bulk triage through the existing per-record actions', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $actor = sharedAdminNoteActor([
        'view_any_support::ticket', 'view_support::ticket', 'update_support::ticket',
        'view_any_report', 'view_report', 'update_report',
    ]);
    $this->actingAs($actor);
    $ticket = SupportTicket::create(['subject' => 'Question', 'body' => 'Need help', 'email' => 'guest@example.test']);
    $reporter = User::factory()->create();
    $report = Report::query()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => User::class,
        'reportable_id' => $reporter->id,
        'reason' => 'other',
        'status' => 'open',
    ]);

    Livewire::test(ListSupportTickets::class)
        ->assertTableBulkActionExists('markSelectedInProgress')
        ->callTableBulkAction('markSelectedInProgress', [$ticket]);
    Livewire::test(ListReports::class)
        ->assertTableBulkActionExists('markSelectedUnderReview')
        ->callTableBulkAction('markSelectedUnderReview', [$report]);

    expect($ticket->fresh()->status->value)->toBe('in_progress')
        ->and($report->fresh()->status->value)->toBe('under_review');
});

it('keeps internal tags bilingual and isolated from other tag types', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $actor = sharedAdminNoteActor(['view_support::ticket', 'update_support::ticket']);
    $ticket = SupportTicket::create(['subject' => 'Question', 'body' => 'Need help', 'email' => 'guest@example.test']);
    $internal = Tag::create(['name' => ['en' => 'Priority', 'ar' => 'أولوية'], 'type' => 'internal_record']);
    $public = Tag::create(['name' => ['en' => 'Public', 'ar' => 'عام'], 'type' => 'public']);

    app(SyncInternalRecordTagsAction::class)->execute($ticket, $actor, [['en' => 'Priority', 'ar' => 'أولوية']]);

    expect($ticket->fresh()->tagsWithType('internal_record')->modelKeys())->toBe([$internal->id])
        ->and($ticket->fresh()->tagsWithType('public')->modelKeys())->toBe([])
        ->and(Tag::query()->whereKey($public->id)->exists())->toBeTrue()
        ->and(fn () => app(SyncInternalRecordTagsAction::class)->execute($ticket, $actor, [['en' => '', 'ar' => 'عاجل']]))
        ->toThrow(InvalidArgumentException::class);
});

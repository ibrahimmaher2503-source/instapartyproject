<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Application\Actions\AddInternalRecordNoteAction;
use App\Modules\Shared\Application\Actions\SyncInternalRecordTagsAction;
use App\Modules\Shared\Domain\Exceptions\AppendOnlyViolationException;
use App\Modules\Shared\Domain\Models\InternalRecordNote;
use App\Modules\Shared\Filament\Resources\RelationManagers\InternalRecordNotesRelationManager;
use App\Modules\Support\Domain\Enums\SupportTicketStatus;
use App\Modules\Support\Domain\Models\SupportTicket;
use App\Modules\Support\Filament\Resources\SupportTicketResource;
use App\Modules\Support\Filament\Resources\SupportTicketResource\Pages\ListSupportTickets;
use App\Modules\Support\Filament\Resources\SupportTicketResource\Pages\ViewSupportTicket;
use App\Modules\TrustSafety\Domain\Enums\ReportStatus;
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

    $ticketOld = SupportTicket::query()->create(['subject' => 'Old open ticket', 'body' => 'Open', 'email' => 'old@example.test']);
    $ticketNew = SupportTicket::query()->create(['subject' => 'New open ticket', 'body' => 'Open', 'email' => 'new@example.test']);
    $ticketTerminal = SupportTicket::query()->create(['subject' => 'Resolved ticket', 'body' => 'Resolved', 'email' => 'done@example.test', 'status' => SupportTicketStatus::Resolved]);
    $ticketOld->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();
    $ticketNew->forceFill(['created_at' => now()->subDay()])->saveQuietly();

    $reporter = User::factory()->create();
    $makeReport = static function (ReportStatus $status, int $days) use ($reporter): Report {
        $record = Report::query()->create([
            'reporter_id' => $reporter->id, 'reportable_type' => User::class,
            'reportable_id' => $reporter->id, 'reason' => 'other', 'status' => $status,
        ]);
        $record->forceFill(['created_at' => now()->subDays($days)])->saveQuietly();

        return $record;
    };
    $reportOld = $makeReport(ReportStatus::Open, 2);
    $reportNew = $makeReport(ReportStatus::UnderReview, 1);
    $reportTerminal = $makeReport(ReportStatus::Resolved, 0);

    $ticketPage = Livewire::test(SupportTicketResource::getPages()['index']->getPage())
        ->assertTableColumnExists('queue_age')
        ->assertTableFilterExists('overdue');
    $reportPage = Livewire::test(ReportResource::getPages()['index']->getPage())
        ->assertTableColumnExists('queue_age')
        ->assertTableFilterExists('overdue');

    expect($ticketPage->instance()->getFilteredSortedTableQuery()->pluck('id')->all())
        ->toBe([$ticketOld->id, $ticketNew->id, $ticketTerminal->id])
        ->and($reportPage->instance()->getFilteredSortedTableQuery()->pluck('id')->all())
        ->toBe([$reportOld->id, $reportNew->id, $reportTerminal->id]);
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

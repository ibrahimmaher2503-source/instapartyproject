<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Filament\Resources\BookingResource\Pages\ViewBooking;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Application\Actions\AddInternalRecordNoteAction;
use App\Modules\Shared\Application\Actions\SyncInternalRecordTagsAction;
use App\Modules\Shared\Domain\Exceptions\AppendOnlyViolationException;
use App\Modules\Shared\Domain\Models\InternalRecordNote;
use App\Modules\Shared\Filament\Resources\RelationManagers\InternalRecordNotesRelationManager;
use App\Modules\Support\Domain\Models\SupportTicket;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Tags\Tag;

it('renders the private notes manager for an authorized admin', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $actor = User::factory()->superAdmin()->create();
    $this->actingAs($actor);

    Livewire::test(InternalRecordNotesRelationManager::class, [
        'ownerRecord' => Booking::factory()->create(),
        'pageClass' => ViewBooking::class,
    ])->assertSuccessful()->assertTableActionExists('addInternalNote');
});

function giveInternalRecordPermissions(User $user, string $record): void
{
    foreach (["view_{$record}", "update_{$record}"] as $ability) {
        $user->givePermissionTo(Permission::findOrCreate($ability, 'web'));
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

it('appends internal notes and rejects attempts to change or remove their history', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $actor = User::factory()->create();
    giveInternalRecordPermissions($actor, 'bookings::monitor');
    $booking = Booking::factory()->create();

    $note = app(AddInternalRecordNoteAction::class)->execute(
        $booking,
        $actor,
        'contact',
        'Called the customer to confirm the event date.',
    );

    expect($note->fresh()->body)->toBe('Called the customer to confirm the event date.')
        ->and($note->fresh()->kind)->toBe('contact')
        ->and($note->fresh()->author_id)->toBe($actor->id)
        ->and($note->fresh()->created_at)->not->toBeNull();

    expect(fn () => $note->update(['body' => 'Changed history']))->toThrow(AppendOnlyViolationException::class)
        ->and(fn () => $note->delete())->toThrow(AppendOnlyViolationException::class)
        ->and($note->fresh()->body)->toBe('Called the customer to confirm the event date.');
});

it('requires both record read and update permission before adding an internal note', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $actor = User::factory()->create();
    $booking = Booking::factory()->create();

    giveInternalRecordPermissions($actor, 'bookings::monitor');
    $actor->revokePermissionTo('update_bookings::monitor');

    expect(fn () => app(AddInternalRecordNoteAction::class)->execute(
        $booking,
        $actor,
        'note',
        'Permission must be checked in the action, not only in the UI.',
    ))->toThrow(AuthorizationException::class)
        ->and(InternalRecordNote::query()->count())->toBe(0);
});

it('rejects unsupported morph subjects even when their policies grant access', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $actor = User::factory()->superAdmin()->create();
    $unsupported = new class extends Model
    {
        protected $table = 'users';
    };
    $unsupported->setRawAttributes(['id' => $actor->id]);

    expect(fn () => app(AddInternalRecordNoteAction::class)->execute(
        $unsupported,
        $actor,
        'note',
        'Unsupported subjects must not become note targets.',
    ))->toThrow(InvalidArgumentException::class)
        ->and(InternalRecordNote::query()->count())->toBe(0);
});

it('only syncs existing internal-record tags and preserves the tag library', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $actor = User::factory()->create();
    giveInternalRecordPermissions($actor, 'support::ticket');
    $ticket = SupportTicket::query()->create([
        'subject' => 'Delivery follow-up',
        'body' => 'Customer asked for an update.',
        'email' => 'customer@example.test',
    ]);
    $tag = Tag::create(['name' => ['en' => 'Urgent', 'ar' => 'عاجل'], 'type' => 'internal_record']);
    $otherType = Tag::create(['name' => ['en' => 'Public', 'ar' => 'عام'], 'type' => 'public']);

    app(SyncInternalRecordTagsAction::class)->execute($ticket, $actor, [[
        'en' => 'Urgent',
        'ar' => 'عاجل',
    ]]);

    expect($ticket->fresh()->tagsWithType('internal_record')->modelKeys())->toBe([$tag->id])
        ->and($ticket->fresh()->tagsWithType('public')->modelKeys())->toBe([])
        ->and(Tag::query()->whereKey($tag->id)->exists())->toBeTrue()
        ->and(Tag::query()->whereKey($otherType->id)->exists())->toBeTrue();
});

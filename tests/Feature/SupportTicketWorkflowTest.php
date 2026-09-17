<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Support\Application\Actions\TransitionSupportTicketStatusAction;
use App\Modules\Support\Domain\Enums\SupportTicketStatus;
use App\Modules\Support\Domain\Models\SupportTicket;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function makeSupportTicket(SupportTicketStatus $status = SupportTicketStatus::Open): SupportTicket
{
    return SupportTicket::query()->create([
        'public_id' => (string) Str::ulid(),
        'subject' => 'QA support ticket',
        'body' => 'Customer needs help with an existing request.',
        'status' => $status,
    ]);
}

function grantSupportUpdate(User $user): void
{
    $user->givePermissionTo(Permission::findOrCreate('update_support::ticket', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

it('requires support update permission before changing a ticket status', function (): void {
    $actor = User::factory()->create();
    $ticket = makeSupportTicket();
    $action = app(TransitionSupportTicketStatusAction::class);

    expect(fn () => $action->execute($ticket, $actor, SupportTicketStatus::InProgress))
        ->toThrow(AuthorizationException::class);

    grantSupportUpdate($actor);

    $updated = $action->execute($ticket, $actor, SupportTicketStatus::InProgress);

    expect($updated->status)->toBe(SupportTicketStatus::InProgress)
        ->and($updated->assigned_to)->toBe($actor->id);
});

it('rejects status transitions outside the existing support workflow', function (): void {
    $actor = User::factory()->create();
    grantSupportUpdate($actor);
    $ticket = makeSupportTicket();

    expect(fn () => app(TransitionSupportTicketStatusAction::class)->execute(
        $ticket,
        $actor,
        SupportTicketStatus::Resolved,
    ))->toThrow(LogicException::class);
});

it('accepts a guest support request from the public contact form', function (): void {
    $this->postJson('/api/v1/customer/support/tickets', [
        'email' => 'guest@example.com',
        'subject' => 'Help with a booking',
        'body' => 'I need help choosing the right service.',
    ], ['Accept-Language' => 'ar'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonStructure(['data' => ['public_id', 'reference', 'message']]);

    $this->assertDatabaseHas('support_tickets', [
        'email' => 'guest@example.com',
        'subject' => 'Help with a booking',
        'status' => 'open',
    ]);
});

it('shows the existing assignee on the admin ticket detail page', function (): void {
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any_support::ticket', 'view_support::ticket'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $ticket = makeSupportTicket(SupportTicketStatus::InProgress);
    $ticket->update(['assigned_to' => $admin->id]);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/support-tickets/'.$ticket->public_id)
        ->assertOk()
        ->assertSee($ticket->public_id)
        ->assertSee($admin->name);
});

<?php

declare(strict_types=1);

use App\Filament\Resources\ActivitylogResource;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Support\Domain\Models\SupportTicket;
use App\Modules\Support\Filament\Resources\SupportTicketResource;
use Filament\Facades\Filament;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('links supported audit subjects only when the administrator can view the record', function (): void {
    $ticket = SupportTicket::create(['subject' => 'Question', 'body' => 'Need help', 'email' => 'guest@example.test']);
    Activity::query()->create([
        'log_name' => 'default',
        'description' => 'Ticket updated',
        'subject_type' => SupportTicket::class,
        'subject_id' => $ticket->id,
        'properties' => [],
        'event' => 'updated',
    ]);
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any_activitylog', 'view_activitylog', 'view_any_support::ticket', 'view_support::ticket'] as $name) {
        $admin->givePermissionTo(Permission::findOrCreate($name, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/activitylogs')
        ->assertOk()
        ->assertSee(SupportTicketResource::getUrl('view', ['record' => $ticket]), false)
        ->assertSee($ticket->public_id);

    expect(ActivitylogResource::canViewAny())->toBeTrue();
});

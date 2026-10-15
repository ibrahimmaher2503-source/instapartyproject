<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Filament\Widgets\AdminDashboardSectionsWidget;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;

it('renders booking chart counts without hydrating incomplete money models', function (): void {
    $this->freezeTime();
    $admin = User::factory()->asAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('view_any_booking', 'web'));
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Booking::factory()->count(2)->create(['created_at' => now()]);

    $data = app(AdminDashboardSectionsWidget::class)->getViewData();
    $series = collect($data['operations']['chart']['series'])
        ->firstWhere('label', __('shared::dashboard.operations.bookings'));

    expect($series)->not->toBeNull()
        ->and(array_column($series['values'], 'value'))->toBe([0, 0, 0, 0, 0, 0, 2]);
    $this->get('/admin')->assertOk();
});

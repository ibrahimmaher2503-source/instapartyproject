<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Filament\Auth\IsolatedPanelLogin;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('renders the admin homepage after an admin signs in', function (): void {
    Role::findOrCreate('admin', 'web');

    $password = 'SafePassword123!';
    $admin = User::factory()->create([
        'email' => 'github100-admin@example.test',
        'password' => $password,
    ]);
    $admin->assignRole('admin');

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(IsolatedPanelLogin::class)
        ->fillForm(['email' => $admin->email, 'password' => $password])
        ->call('authenticate')
        ->assertRedirect(Filament::getPanel('admin')->getUrl());

    expect(auth('web')->id())->toBe($admin->id);

    $this->get('/admin')->assertOk();
});

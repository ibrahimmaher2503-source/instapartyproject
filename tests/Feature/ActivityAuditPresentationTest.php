<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use Filament\Facades\Filament;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function grantActivityView(User $user): void
{
    foreach (['view_any_activitylog', 'view_activitylog'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

function makeLegacyActivity(User $subject, User $causer): Activity
{
    return Activity::query()->create([
        'log_name' => 'default',
        'description' => 'Synthetic activity fixture',
        'subject_type' => User::class,
        'subject_id' => $subject->id,
        'causer_type' => User::class,
        'causer_id' => $causer->id,
        'properties' => [],
        'event' => null,
    ]);
}

it('protects the activity log and avoids legacy internal identifiers', function (): void {
    $admin = User::factory()->state(['preferred_locale' => 'en'])->superAdmin()->create();
    grantActivityView($admin);
    makeLegacyActivity($admin, $admin);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/activitylogs')
        ->assertOk()
        ->assertSee('Legacy / Unknown')
        ->assertSee($admin->public_id)
        ->assertDontSee('#'.$admin->id);
});

it('localizes the legacy activity label', function (): void {
    $admin = User::factory()->state(['preferred_locale' => 'ar'])->superAdmin()->create();
    grantActivityView($admin);
    makeLegacyActivity($admin, $admin);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/activitylogs?lang=ar')
        ->assertOk()
        ->assertSee('قديم / غير معروف');
});

it('denies activity log access without the view permission', function (): void {
    $admin = User::factory()->create();

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    expect($this->get('/admin/activitylogs')->status())->toBeIn([302, 403]);
});

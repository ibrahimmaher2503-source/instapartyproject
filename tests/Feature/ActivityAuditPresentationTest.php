<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Filament\ActivityLog\ActivityLogPresenter;
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
        // Represent an older audit row whose subject was deleted or is no longer resolvable.
        'subject_id' => $subject->id + 999999,
        'causer_type' => User::class,
        'causer_id' => $causer->id,
        'properties' => [],
        'event' => null,
    ]);
}

function makeChangedActivity(User $subject, User $causer): Activity
{
    return Activity::query()->create([
        'log_name' => 'default',
        'description' => 'Updated customer profile',
        'subject_type' => User::class,
        'subject_id' => $subject->id,
        'causer_type' => User::class,
        'causer_id' => $causer->id,
        'properties' => [
            'old' => [
                'name' => 'Previous Display Name',
                'password' => 'old-secret-value',
                'api_token' => 'old-token-value',
            ],
            'attributes' => [
                'name' => 'Updated Display Name',
                'password' => 'new-secret-value',
                'api_token' => 'new-token-value',
            ],
        ],
        'event' => 'updated',
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
        ->assertDontSee('#'.($admin->id + 999999));
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

it('shows safe before and after values, hides credentials, and links only by a viewable public id', function (): void {
    $admin = User::factory()->state(['preferred_locale' => 'en'])->superAdmin()->create();
    grantActivityView($admin);
    $admin->givePermissionTo(Permission::findOrCreate('view_user', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $activity = makeChangedActivity($admin, $admin)->load('subject');

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $response = $this->get('/admin/activitylogs')->assertOk()
        ->assertSee('Previous Display Name')
        ->assertSee('Updated Display Name')
        ->assertDontSee('old-secret-value')
        ->assertDontSee('new-secret-value')
        ->assertDontSee('old-token-value')
        ->assertDontSee('new-token-value')
        ->assertDontSee('#'.$admin->id);

    $url = ActivityLogPresenter::recordUrl($activity);
    expect($url)->not->toBeNull()
        ->and($url)->toContain($admin->public_id)
        ->and(preg_match('~/'.preg_quote((string) $admin->id, '~').'/?$~', (string) parse_url($url, PHP_URL_PATH)))->toBe(0);
});

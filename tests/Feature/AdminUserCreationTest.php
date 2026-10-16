<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Actions\AdminSendUserPasswordResetLinkAction;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Filament\Resources\UserResource\Pages\ListUsers;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('lets an authorized super admin create an account, set verified contacts, and skip the invite email', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin, 'web');

    $reset = Mockery::mock(AdminSendUserPasswordResetLinkAction::class);
    $reset->shouldNotReceive('execute');
    app()->instance(AdminSendUserPasswordResetLinkAction::class, $reset);

    Livewire::test(ListUsers::class)
        ->assertActionVisible('createAccount')
        ->callAction('createAccount', data: [
            'name' => 'New Customer',
            'email' => 'new-customer@example.test',
            'phone_e164' => '+201055550123',
            'account_type' => 'customer',
            'verify_email' => true,
            'verify_phone' => true,
            'send_invite' => false,
        ])
        ->assertHasNoActionErrors();

    $user = User::query()->where('email', 'new-customer@example.test')->sole();
    $audit = DB::table('audit_logs')->where('action', 'managed_account_created')
        ->where('auditable_id', $user->id)->sole();

    expect($user->hasRole('customer'))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->phone_verified_at)->not->toBeNull()
        ->and($user->accepted_terms_at)->toBeNull()
        ->and($user->customerProfile->accepts_marketing)->toBeFalse()
        ->and(Hash::check('password', $user->password))->toBeFalse()
        ->and($audit->user_id)->toBe($admin->id)
        ->and(json_decode($audit->changes, true)['email_verified'])->toBeTrue()
        ->and(json_decode($audit->changes, true)['phone_verified'])->toBeTrue()
        ->and(DB::table('audit_logs')->where('auditable_id', $user->id)
            ->where('action', 'user_password_reset_link_requested')->exists())->toBeFalse();
});

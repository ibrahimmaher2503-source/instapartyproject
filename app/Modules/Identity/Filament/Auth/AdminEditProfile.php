<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Auth;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Auth\EditProfile;
use Illuminate\Validation\Rules\Password;

final class AdminEditProfile extends EditProfile
{
    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->rule(Password::min(8)->mixedCase()->numbers()->symbols());
    }

    /**
     * @return array<int | string, string | Form>
     */
    protected function getForms(): array
    {
        $requiresCurrentPassword = fn (Get $get): bool => filled($get('password'))
            || $get('email') !== $this->getUser()->email;

        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                        TextInput::make('current_password')
                            ->label(__('account.current_password'))
                            ->password()
                            ->autocomplete('current-password')
                            ->required($requiresCurrentPassword)
                            ->currentPassword($requiresCurrentPassword)
                            ->validationMessages([
                                'current_password' => __('account.password_invalid'),
                            ])
                            ->dehydrated(false),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                    ])
                    ->operation('edit')
                    ->model($this->getUser())
                    ->statePath('data')
                    ->inlineLabel(! self::isSimple()),
            ),
        ];
    }
}

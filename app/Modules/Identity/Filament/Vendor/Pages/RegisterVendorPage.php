<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Vendor\Pages;

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Application\Actions\RegisterVendorAction;
use App\Modules\Identity\Application\DTOs\RegisterVendorDTO;
use App\Modules\Identity\Application\Services\VendorRegistrationRules;
use App\Modules\Identity\Domain\Enums\BusinessType;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Http\Responses\Auth\Contracts\RegistrationResponse;
use Filament\Pages\Auth\Register;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegisterVendorPage extends Register
{
    public function validate($rules = null, $messages = [], $attributes = []): array
    {
        // Public Livewire validation must never accept caller-supplied database rules.
        return parent::validate($this->form->getValidationRules(), $this->form->getValidationMessages(), $this->form->getValidationAttributes());
    }

    public function validateOnly($field, $rules = null, $messages = [], $attributes = [], $dataOverrides = [])
    {
        return parent::validateOnly($field, $this->form->getValidationRules(), $this->form->getValidationMessages(), $this->form->getValidationAttributes());
    }

    protected function beforeValidate(): void
    {
        $this->data = VendorRegistrationRules::normalize($this->data);
    }

    public function register(): ?RegistrationResponse
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->clearSensitiveRegistrationState();
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        try {
            $this->wrapInDatabaseTransaction(function (): Model {
                $this->callHook('beforeValidate');
                $data = $this->form->getState();
                $this->callHook('afterValidate');
                $data = $this->mutateFormDataBeforeRegister($data);
                $this->callHook('beforeRegister');
                $user = $this->handleRegistration($data);
                $this->form->model($user)->saveRelationships();
                $this->callHook('afterRegister');

                return $user;
            });
        } catch (ValidationException $exception) {
            $duplicateEmailMessage = __('identity::identity.validation.duplicate_email');
            $hasDuplicateEmail = false;
            $remainingErrors = [];

            foreach ($exception->errors() as $field => $messages) {
                $isEmailField = $field === 'email' || str_ends_with($field, '.email');
                $filteredMessages = [];

                foreach ($messages as $message) {
                    if ($isEmailField && $message === $duplicateEmailMessage) {
                        $hasDuplicateEmail = true;

                        continue;
                    }

                    $filteredMessages[] = $message;
                }

                if ($filteredMessages !== []) {
                    $remainingErrors[$field] = $filteredMessages;
                }
            }

            if (! $hasDuplicateEmail) {
                throw $exception;
            }

            $remainingErrorBag = [];
            foreach ($this->getErrorBag()->getMessages() as $field => $messages) {
                $isEmailField = $field === 'email' || str_ends_with($field, '.email');
                $filteredMessages = array_values(array_filter(
                    $messages,
                    static fn (string $message): bool => ! ($isEmailField && $message === $duplicateEmailMessage),
                ));

                if ($filteredMessages !== []) {
                    $remainingErrorBag[$field] = $filteredMessages;
                }
            }
            $this->setErrorBag($remainingErrorBag);

            if ($remainingErrors !== []) {
                throw ValidationException::withMessages($remainingErrors);
            }

            return app(RegistrationResponse::class);
        } finally {
            $this->clearSensitiveRegistrationState();
        }

        return app(RegistrationResponse::class);
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->components([
                        Section::make()
                            ->schema([
                                $this->getNameFormComponent(),
                                $this->getEmailFormComponent(),
                                $this->getPhoneFormComponent(),
                                $this->getPasswordFormComponent(),
                                $this->getPasswordConfirmationFormComponent(),
                            ]),

                        Section::make(__('vendor-portal.profile.business_name'))
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('business_name_en')
                                        ->label(__('identity.forms.business_name_en'))
                                        ->required()
                                        ->maxLength(200),
                                    TextInput::make('business_name_ar')
                                        ->label(__('identity.forms.business_name_ar'))
                                        ->required()
                                        ->maxLength(200),
                                ]),
                                Grid::make(2)->schema([
                                    Select::make('business_type')
                                        ->label(__('identity.fields.business_type'))
                                        ->options([
                                            BusinessType::Individual->value => __('identity.business_type.individual'),
                                            BusinessType::Company->value => __('identity.business_type.company'),
                                            BusinessType::Establishment->value => __('identity.business_type.establishment'),
                                        ])
                                        ->required(),
                                    Select::make('primary_governorate_id')
                                        ->label(__('identity.fields.governorate_id'))
                                        ->options(fn (): array => Governorate::query()
                                            ->active()
                                            ->orderBy('sort_order')
                                            ->get()
                                            ->mapWithKeys(fn (Governorate $governorate): array => [
                                                $governorate->id => $governorate->getTranslation('name', app()->getLocale()),
                                            ])
                                            ->all())
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(fn (callable $set) => $set('primary_city_id', null)),
                                    Select::make('primary_city_id')
                                        ->label(__('identity.fields.city_id'))
                                        ->options(fn (callable $get): array => City::query()
                                            ->active()
                                            ->where('governorate_id', $get('primary_governorate_id'))
                                            ->orderBy('sort_order')
                                            ->get()
                                            ->mapWithKeys(fn (City $city): array => [
                                                $city->id => $city->getTranslation('name', app()->getLocale()),
                                            ])
                                            ->all())
                                        ->required()
                                        ->searchable(),
                                ]),
                            ]),

                        Section::make(__('vendor-portal.profile.social_links'))
                            ->columns(3)
                            ->schema([
                                TextInput::make('facebook_url')
                                    ->label(__('vendor-portal.profile.facebook_url'))
                                    ->url()
                                    ->validationMessages(['url' => __('vendor-portal.profile.invalid_url')])
                                    ->maxLength(2048),
                                TextInput::make('website_url')
                                    ->label(__('vendor-portal.profile.website_url'))
                                    ->url()
                                    ->validationMessages(['url' => __('vendor-portal.profile.invalid_url')])
                                    ->maxLength(2048),
                                TextInput::make('instagram_url')
                                    ->label(__('vendor-portal.profile.instagram_url'))
                                    ->url()
                                    ->validationMessages(['url' => __('vendor-portal.profile.invalid_url')])
                                    ->maxLength(2048),
                            ]),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('filament-panels::pages/auth/register.form.email.label'))
            ->email()
            ->required()
            ->maxLength(255)
            ->validationAttribute(__('identity::identity.fields.email'));
    }

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone_e164')
            ->label(__('identity.fields.phone'))
            ->placeholder('+201234567890')
            ->required()
            ->maxLength(20)
            ->regex(VendorRegistrationRules::PHONE_E164_REGEX)
            ->rule(Rule::unique('users', 'phone_e164'))
            ->validationMessages([
                'required' => __('identity::identity.validation.phone_required'),
                'regex' => __('identity::identity.validation.phone_e164'),
                'unique' => __('identity::identity.validation.duplicate_phone'),
            ])
            ->helperText(__('identity::identity.registration.phone_hint'))
            ->validationAttribute(__('identity::identity.fields.phone'));
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return parent::getPasswordConfirmationFormComponent()->dehydrated();
    }

    // Override to skip hashing — RegisterVendorAction hashes internally.
    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('filament-panels::pages/auth/register.form.password.label'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->rule(VendorRegistrationRules::passwordRule())
            ->same('passwordConfirmation')
            ->helperText(__('vendor-portal.registration.password_hint'))
            ->validationAttribute(__('filament-panels::pages/auth/register.form.password.validation_attribute'));
    }

    protected function handleRegistration(array $data): Model
    {
        $dto = RegisterVendorDTO::fromArray([
            ...$data,
            'password_confirmation' => $data['passwordConfirmation'],
            'business_name' => [
                'en' => $data['business_name_en'],
                'ar' => $data['business_name_ar'],
            ],
            'preferred_locale' => app()->getLocale(),
        ]);

        $vendorProfile = app(RegisterVendorAction::class)->execute($dto);

        return $vendorProfile->user;
    }

    private function clearSensitiveRegistrationState(): void
    {
        if ($this->data === null) {
            return;
        }

        unset($this->data['password'], $this->data['passwordConfirmation'], $this->data['password_confirmation']);
    }
}

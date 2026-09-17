<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Vendor\Pages;

use App\Modules\Identity\Application\Actions\SendOtpAction;
use App\Modules\Identity\Application\Actions\VerifyPhoneAction;
use App\Modules\Identity\Domain\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class VendorPhoneVerificationPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationGroup = 'profile';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'vendor-portal.pages.vendor-phone-verification';

    public ?array $data = [];

    public bool $isVerified = false;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && $user->hasRole('vendor')
            && $user->vendorProfile !== null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.phone_verification.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.phone_verification.title');
    }

    public function mount(): void
    {
        $user = $this->getUser();
        $this->isVerified = $user->phone_verified_at !== null;

        $this->form->fill([
            'phone_e164' => $user->phone_e164,
            'code' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->components([
                Section::make(__('vendor-portal.phone_verification.title'))
                    ->description(__('vendor-portal.phone_verification.description'))
                    ->schema([
                        TextInput::make('phone_e164')
                            ->label(__('vendor-portal.phone_verification.phone'))
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('code')
                            ->label(__('vendor-portal.phone_verification.code'))
                            ->numeric()
                            ->length(6)
                            ->required(fn (): bool => ! $this->isVerified)
                            ->visible(fn (): bool => ! $this->isVerified),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendCode')
                ->label(__('vendor-portal.phone_verification.send_code'))
                ->icon('heroicon-o-paper-airplane')
                ->action('sendCode')
                ->visible(fn (): bool => ! $this->isVerified),
        ];
    }

    public function sendCode(): void
    {
        $user = $this->getUser();

        if ($this->isVerified) {
            Notification::make()
                ->title(__('vendor-portal.phone_verification.already_verified'))
                ->success()
                ->send();

            return;
        }

        if (blank($user->phone_e164)) {
            Notification::make()
                ->title(__('vendor-portal.phone_verification.phone_required'))
                ->danger()
                ->send();

            return;
        }

        app(SendOtpAction::class)->execute($user->phone_e164);

        Notification::make()
            ->title(__('vendor-portal.phone_verification.code_sent'))
            ->success()
            ->send();
    }

    public function verifyPhone(): void
    {
        $user = $this->getUser();

        if ($this->isVerified) {
            Notification::make()
                ->title(__('vendor-portal.phone_verification.already_verified'))
                ->success()
                ->send();

            return;
        }

        $data = $this->form->getState();
        app(VerifyPhoneAction::class)->execute($user->phone_e164, (string) ($data['code'] ?? ''));
        $this->isVerified = true;
        $this->form->fill([
            'phone_e164' => $user->phone_e164,
            'code' => null,
        ]);

        Notification::make()
            ->title(__('vendor-portal.phone_verification.verified'))
            ->success()
            ->send();
    }

    private function getUser(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}

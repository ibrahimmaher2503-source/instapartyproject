<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Pages;

use App\Modules\Communication\Application\Actions\SaveProviderSettingsAction;
use App\Modules\Communication\Application\Services\FirebaseCredentialsValidator;
use App\Modules\Communication\Application\Services\ProviderSettingsResolver;
use App\Modules\Communication\Domain\Enums\ProviderName;
use App\Modules\Communication\Domain\Models\CommunicationProviderSetting;
use App\Modules\Communication\Infrastructure\Gateways\FcmPushAdapter;
use App\Modules\Communication\Infrastructure\Gateways\SmsMisrProvider;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

final class ManageProviderSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?int $navigationSort = 35;

    protected static string $view = 'communication::filament.pages.manage-provider-settings';

    public array $data = [];

    public array $providerStatus = [];

    public ?string $smsBalance = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') === true
            && auth()->user()?->can('manage_notification_providers') === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('communication.provider_settings.nav_label');
    }

    public function getTitle(): string
    {
        return __('communication.provider_settings.title');
    }

    public function mount(ProviderSettingsResolver $resolver): void
    {
        $firebase = $resolver->firebase();
        $sms = $resolver->smsMisr();

        $this->form->fill([
            'firebase_enabled' => $firebase['enabled'],
            'firebase_project_id' => $firebase['project_id'],
            'firebase_credentials_masked' => $resolver->mask($this->credentialString($firebase['credentials'])),
            'sms_enabled' => $sms['enabled'],
            'sms_username' => $sms['username'],
            'sms_password_masked' => $resolver->mask($sms['password']),
            'sms_sender_id' => $sms['sender'],
            'sms_environment' => $sms['environment'],
        ]);

        $this->refreshStatus();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('communication.provider_settings.firebase.heading'))
                ->description(__('communication.provider_settings.firebase.description'))
                ->columns(2)
                ->schema([
                    Toggle::make('firebase_enabled')->label(__('communication.provider_settings.enabled')),
                    TextInput::make('firebase_project_id')->label(__('communication.provider_settings.firebase.project_id'))->maxLength(190),
                    TextInput::make('firebase_credentials_masked')->label(__('communication.provider_settings.secret'))->disabled()->dehydrated(false)->placeholder('••••••••••'),
                    FileUpload::make('firebase_credentials_file')->label(__('communication.provider_settings.firebase.replace_credentials'))->acceptedFileTypes(['application/json'])->storeFiles(false)->dehydrated(false),
                    Textarea::make('firebase_credentials_json')->label(__('communication.provider_settings.firebase.credentials_json'))->helperText(__('communication.provider_settings.firebase.credentials_help'))->rows(5)->dehydrated(false)->columnSpanFull(),
                ]),
            Section::make(__('communication.provider_settings.sms.heading'))
                ->description(__('communication.provider_settings.sms.description'))
                ->columns(2)
                ->schema([
                    Toggle::make('sms_enabled')->label(__('communication.provider_settings.enabled')),
                    TextInput::make('sms_username')->label(__('communication.provider_settings.sms.username'))->maxLength(190),
                    TextInput::make('sms_password_masked')->label(__('communication.provider_settings.secret'))->disabled()->dehydrated(false)->placeholder('••••••••••'),
                    TextInput::make('sms_password')->label(__('communication.provider_settings.sms.replace_password'))->password()->revealable()->dehydrated(false),
                    TextInput::make('sms_sender_id')->label(__('communication.provider_settings.sms.sender_id'))->maxLength(120),
                    Select::make('sms_environment')->label(__('communication.provider_settings.sms.environment'))->options([2 => 'Test (2)', 1 => 'Production (1)'])->required(),
                    TextInput::make('sms_test_mobile')->label(__('communication.provider_settings.sms.test_mobile'))->tel()->dehydrated(false),
                ]),
        ])->statePath('data');
    }

    public function save(SaveProviderSettingsAction $action): void
    {
        $data = $this->form->getState();
        $firebaseCredentials = $this->readFirebaseCredentials($data);

        $action->execute(ProviderName::Firebase, [
            'enabled' => (bool) ($data['firebase_enabled'] ?? false),
            'project_id' => $data['firebase_project_id'] ?? null,
            ...($firebaseCredentials === null ? [] : ['credentials' => $firebaseCredentials]),
        ], auth()->id());

        $smsPassword = filled($data['sms_password'] ?? null) ? $data['sms_password'] : null;
        $action->execute(ProviderName::SmsMisr, [
            'enabled' => (bool) ($data['sms_enabled'] ?? false),
            'username' => $data['sms_username'] ?? null,
            'environment' => (int) ($data['sms_environment'] ?? 2),
            'sender_id' => $data['sms_sender_id'] ?? null,
            ...($smsPassword === null ? [] : ['password' => $smsPassword]),
        ], auth()->id());

        Notification::make()->title(__('communication.provider_settings.saved'))->success()->send();
        $this->refreshStatus();
    }

    public function checkSmsBalance(SmsMisrProvider $provider): void
    {
        try {
            $result = $provider->checkBalance();
            $this->smsBalance = (string) ($result['balance'] ?? $result['Balance'] ?? $result['balance_value'] ?? '');
            $this->recordStatus(ProviderName::SmsMisr, 'reachable');
            Notification::make()->title(__('communication.provider_settings.sms.balance_checked'))->body($this->smsBalance)->success()->send();
        } catch (\Throwable $e) {
            $this->recordStatus(ProviderName::SmsMisr, 'unreachable', class_basename($e));
            Notification::make()->title(__('communication.provider_settings.connection_failed'))->body(class_basename($e))->danger()->send();
        }
    }

    public function testSms(SmsMisrProvider $provider): void
    {
        $mobile = $this->form->getState()['sms_test_mobile'] ?? null;

        if (blank($mobile)) {
            Notification::make()->title(__('communication.provider_settings.sms.test_mobile_required'))->danger()->send();

            return;
        }

        try {
            $provider->send($mobile, __('communication.provider_settings.sms.test_message'), 2);
            $this->recordStatus(ProviderName::SmsMisr, 'reachable');
            Notification::make()->title(__('communication.provider_settings.sms.test_sent'))->success()->send();
        } catch (\Throwable $e) {
            $this->recordStatus(ProviderName::SmsMisr, 'unreachable', class_basename($e));
            Notification::make()->title(__('communication.provider_settings.connection_failed'))->body(class_basename($e))->danger()->send();
        }
    }

    public function testFirebase(FcmPushAdapter $provider): void
    {
        try {
            $health = $provider->healthCheck();
            $status = $health->isReachable ? 'reachable' : 'unreachable';
            $this->recordStatus(ProviderName::Firebase, $status, $health->note);
            $notification = Notification::make()->title($health->isReachable ? __('communication.provider_settings.connection_success') : __('communication.provider_settings.connection_failed'));
            $health->isReachable ? $notification->success() : $notification->danger();
            $notification->send();
        } catch (\Throwable $e) {
            $this->recordStatus(ProviderName::Firebase, 'unreachable', class_basename($e));
            Notification::make()->title(__('communication.provider_settings.connection_failed'))->body(class_basename($e))->danger()->send();
        }
    }

    public function refreshStatus(): void
    {
        $rows = CommunicationProviderSetting::query()->get()->keyBy('provider');
        $resolver = app(ProviderSettingsResolver::class);
        $resolved = [
            ProviderName::Firebase->value => $resolver->firebase(),
            ProviderName::SmsMisr->value => $resolver->smsMisr(),
        ];
        $this->providerStatus = [];

        foreach (ProviderName::cases() as $provider) {
            $row = $rows->get($provider->value);
            $settings = $resolved[$provider->value];
            $this->providerStatus[$provider->value] = [
                'enabled' => (bool) $settings['enabled'],
                'configured' => $provider === ProviderName::Firebase
                    ? filled($settings['credentials']) && filled($settings['project_id'])
                    : filled($settings['username']) && filled($settings['password']) && filled($settings['sender']),
                'status' => $row?->last_connection_status ?? 'not_checked',
                'checked_at' => $row?->last_checked_at?->diffForHumans(),
            ];
        }
    }

    /** @param array<string,mixed> $data @return array<string,mixed>|null */
    private function readFirebaseCredentials(array $data): ?array
    {
        $file = $data['firebase_credentials_file'] ?? null;
        $json = $data['firebase_credentials_json'] ?? null;
        if (is_array($file)) {
            $file = $file[0] ?? null;
        }
        $raw = $file !== null && method_exists($file, 'getRealPath') ? file_get_contents($file->getRealPath()) : $json;

        if (blank($raw)) {
            return null;
        }

        return app(FirebaseCredentialsValidator::class)->validate($raw);
    }

    /** @param array<string,mixed>|null $credentials */
    private function credentialString(?array $credentials): ?string
    {
        return $credentials === null ? null : json_encode($credentials, JSON_THROW_ON_ERROR);
    }

    private function recordStatus(ProviderName $provider, string $status, ?string $error = null): void
    {
        CommunicationProviderSetting::query()->updateOrCreate(
            ['provider' => $provider->value],
            ['last_connection_status' => $status, 'last_connection_error' => $error, 'last_checked_at' => now()],
        );

        $this->refreshStatus();
    }
}

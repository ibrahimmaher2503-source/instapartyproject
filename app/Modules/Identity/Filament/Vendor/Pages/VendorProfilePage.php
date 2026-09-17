<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Vendor\Pages;

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Application\Actions\UpdateVendorProfileAction;
use App\Modules\Identity\Application\Support\MaskBankData;
use App\Modules\Identity\Domain\Enums\BusinessType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class VendorProfilePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'profile';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'vendor-portal.pages.vendor-profile';

    public ?array $data = [];

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.profile.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.profile.title');
    }

    public function mount(): void
    {
        $profile = $this->getVendorProfile();

        $this->form->fill([
            'business_name_en' => $profile->getTranslation('business_name', 'en'),
            'business_name_ar' => $profile->getTranslation('business_name', 'ar'),
            'bio_en' => $profile->getTranslation('bio', 'en'),
            'bio_ar' => $profile->getTranslation('bio', 'ar'),
            'address_line_en' => $profile->getTranslation('address_line', 'en'),
            'address_line_ar' => $profile->getTranslation('address_line', 'ar'),
            'commercial_register_no' => $profile->commercial_register_no,
            'tax_id' => $profile->tax_id,
            'national_id' => $profile->national_id,
            'primary_governorate_id' => $profile->primary_governorate_id,
            'primary_city_id' => $profile->primary_city_id,
            'bank_name' => filled($profile->getRawOriginal('bank_name'))
                ? MaskBankData::forField('bank_name', $profile->getRawOriginal('bank_name'))
                : null,
            'bank_account_holder' => filled($profile->getRawOriginal('bank_account_holder'))
                ? MaskBankData::forField('bank_account_holder', $profile->getRawOriginal('bank_account_holder'))
                : null,
            'bank_iban' => filled($profile->getRawOriginal('bank_iban'))
                ? MaskBankData::forField('bank_iban', $profile->getRawOriginal('bank_iban'))
                : null,
            'bank_swift' => filled($profile->getRawOriginal('bank_swift_bic'))
                ? MaskBankData::forField('bank_swift_bic', $profile->getRawOriginal('bank_swift_bic'))
                : null,
            // logo_path intentionally omitted — FileUpload must start empty to avoid
            // Filepond treating the stored path as a pending upload and blocking submit.
            // The current logo is shown via the Placeholder below.
        ]);
    }

    public function form(Form $schema): Form
    {
        return $schema
            ->components([
                Section::make(__('vendor-portal.profile.title'))
                    ->schema([
                        Placeholder::make('current_logo_preview')
                            ->label(__('vendor-portal.profile.logo'))
                            ->content(function (): HtmlString {
                                $logoPath = $this->getVendorProfile()->logo_path;
                                if (! $logoPath) {
                                    return new HtmlString(
                                        '<span class="text-sm text-gray-400">'.__('vendor-portal.profile.no_logo').'</span>'
                                    );
                                }

                                return new HtmlString(
                                    '<img src="'.e(Storage::url($logoPath)).'" alt="Business logo"'
                                    .' class="h-20 w-20 rounded-full object-cover border border-gray-200">'
                                );
                            })
                            ->columnSpanFull(),
                        FileUpload::make('logo_path')
                            ->label(__('vendor-portal.profile.logo_new'))
                            ->disk('public')
                            ->directory('vendor-logos')
                            ->image()
                            ->imageEditor()
                            ->maxSize(2048)
                            ->columnSpanFull(),
                        Tabs::make('translations')
                            ->columnSpanFull()
                            ->tabs([
                                Tab::make('English')
                                    ->schema([
                                        TextInput::make('business_name_en')
                                            ->label(__('vendor-portal.profile.business_name'))
                                            ->required()
                                            ->maxLength(200),
                                        Textarea::make('bio_en')
                                            ->label(__('vendor-portal.profile.bio'))
                                            ->rows(3),
                                        TextInput::make('address_line_en')
                                            ->label(__('vendor-portal.profile.address_line'))
                                            ->maxLength(255),
                                    ]),
                                Tab::make('العربية')
                                    ->schema([
                                        TextInput::make('business_name_ar')
                                            ->label(__('vendor-portal.profile.business_name'))
                                            ->required()
                                            ->maxLength(200),
                                        Textarea::make('bio_ar')
                                            ->label(__('vendor-portal.profile.bio'))
                                            ->rows(3),
                                        TextInput::make('address_line_ar')
                                            ->label(__('vendor-portal.profile.address_line'))
                                            ->required()
                                            ->maxLength(255),
                                    ]),
                            ]),
                    ]),

                Section::make(__('identity.sections.business_profile'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('national_id')
                            ->label(__('identity.fields.national_id'))
                            ->maxLength(50)
                            ->visible(fn (): bool => $this->getVendorProfile()->business_type === BusinessType::Individual)
                            ->required(fn (): bool => $this->getVendorProfile()->business_type === BusinessType::Individual),
                        TextInput::make('commercial_register_no')
                            ->label(__('identity.fields.commercial_register_no'))
                            ->maxLength(50)
                            ->visible(fn (): bool => in_array($this->getVendorProfile()->business_type, [BusinessType::Company, BusinessType::Establishment], true))
                            ->required(fn (): bool => in_array($this->getVendorProfile()->business_type, [BusinessType::Company, BusinessType::Establishment], true)),
                        TextInput::make('tax_id')
                            ->label(__('identity.fields.tax_id'))
                            ->maxLength(50)
                            ->visible(fn (): bool => $this->getVendorProfile()->business_type === BusinessType::Company)
                            ->required(fn (): bool => $this->getVendorProfile()->business_type === BusinessType::Company),
                    ]),

                Section::make(__('vendor-portal.profile.location'))
                    ->columns(2)
                    ->schema([
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
                            ->options(
                                fn (callable $get): array => $get('primary_governorate_id')
                                    ? City::query()
                                        ->where('governorate_id', $get('primary_governorate_id'))
                                        ->active()
                                        ->orderBy('sort_order')
                                        ->get()
                                        ->mapWithKeys(fn (City $city): array => [
                                            $city->id => $city->getTranslation('name', app()->getLocale()),
                                        ])
                                        ->all()
                                    : []
                            )
                            ->required(),
                    ]),

                Section::make(__('identity.sections.bank_information'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('bank_name')
                            ->label(__('vendor-portal.profile.bank_name'))
                            ->maxLength(100),
                        TextInput::make('bank_account_holder')
                            ->label(__('vendor-portal.profile.bank_holder_name'))
                            ->maxLength(150),
                        TextInput::make('bank_iban')
                            ->label(__('vendor-portal.profile.bank_iban'))
                            ->maxLength(34)
                            ->rule(
                                'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/',
                                fn (TextInput $component): bool => filled($component->getState())
                                    && (
                                        blank($this->getVendorProfile()->getRawOriginal('bank_iban'))
                                        || (string) $component->getState() !== MaskBankData::forField(
                                            'bank_iban',
                                            $this->getVendorProfile()->getRawOriginal('bank_iban'),
                                        )
                                    ),
                            )
                            ->validationMessages(['regex' => 'Must be a valid IBAN (e.g. EG12 1234 5678 …)'])
                            ->placeholder('EG...'),
                        TextInput::make('bank_swift')
                            ->label(__('vendor-portal.profile.bank_swift'))
                            ->maxLength(11),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $profile = $this->getVendorProfile();

        $logoPaths = $data['logo_path'] ?? [];
        $newLogoPath = is_array($logoPaths) ? (reset($logoPaths) ?: null) : ($logoPaths ?: null);

        $payload = [
            'business_name' => ['en' => $data['business_name_en'], 'ar' => $data['business_name_ar']],
            'bio' => ['en' => $data['bio_en'] ?? '', 'ar' => $data['bio_ar'] ?? ''],
            'address_line' => ['en' => $data['address_line_en'] ?? '', 'ar' => $data['address_line_ar'] ?? ''],
            'commercial_register_no' => $data['commercial_register_no'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'national_id' => $data['national_id'] ?? null,
            'primary_governorate_id' => $data['primary_governorate_id'],
            'primary_city_id' => $data['primary_city_id'],
            'bank_name' => $data['bank_name'],
            'bank_account_holder' => $data['bank_account_holder'],
            'bank_iban' => $data['bank_iban'],
            'bank_swift_bic' => $data['bank_swift'],
        ];

        foreach (['bank_name', 'bank_account_holder', 'bank_iban', 'bank_swift_bic'] as $field) {
            $inputField = $field === 'bank_swift_bic' ? 'bank_swift' : $field;

            if (($data[$inputField] ?? null) === MaskBankData::forField($field, $profile->getRawOriginal($field))) {
                unset($payload[$field]);
            }
        }

        // Only overwrite the stored logo when the vendor actually uploads a new one.
        if ($newLogoPath !== null) {
            $payload['logo_path'] = $newLogoPath;
        }

        app(UpdateVendorProfileAction::class)->execute($profile, $payload);

        Notification::make()
            ->title(__('vendor-portal.profile.saved'))
            ->success()
            ->send();
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}

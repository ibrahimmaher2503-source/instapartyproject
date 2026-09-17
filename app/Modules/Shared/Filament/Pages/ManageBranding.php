<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Pages;

use App\Modules\Shared\Application\Actions\SaveBrandingAction;
use App\Modules\Shared\Domain\Models\BrandingSetting;
use App\Modules\Shared\Filament\Resources\DesignTokenResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageBranding extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?int $navigationSort = 20;

    protected static bool $shouldRegisterNavigation = true;

    protected static string $view = 'filament.pages.manage-branding';

    public ?array $data = [];

    public ?BrandingSetting $record = null;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.appearance');
    }

    public static function getNavigationLabel(): string
    {
        return __('shared.branding.nav_label');
    }

    public function getTitle(): string
    {
        return __('shared.branding.title');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageColors')
                ->label(__('shared.branding.manage_colors'))
                ->icon('heroicon-o-swatch')
                ->url(DesignTokenResource::getUrl(panel: 'admin')),
        ];
    }

    public function mount(): void
    {
        $this->record = BrandingSetting::current();

        $this->form->fill([
            'site_name' => $this->record->site_name,
            'tagline' => $this->record->tagline,
            'support_email' => $this->record->support_email,
            'support_phone' => $this->record->support_phone,
            'whatsapp_number' => $this->record->whatsapp_number,
            'social' => (array) ($this->record->social ?? []),
            'address_line' => $this->record->address_line,
        ]);
    }

    public function form(Form $schema): Form
    {
        return $schema
            ->model($this->record)
            ->statePath('data')
            ->components([
                Section::make(__('shared.branding.identity'))
                    ->columns(2)
                    ->schema([
                        Tabs::make('Translations')
                            ->columnSpanFull()
                            ->tabs([
                                Tab::make('English')->schema([
                                    TextInput::make('site_name.en')->label(__('shared.branding.site_name_en'))->required()->maxLength(120),
                                    TextInput::make('tagline.en')->label(__('shared.branding.tagline_en'))->maxLength(255),
                                    TextInput::make('address_line.en')->label(__('shared.branding.address_en'))->maxLength(255),
                                ]),
                                Tab::make('العربية')->schema([
                                    TextInput::make('site_name.ar')->label('اسم الموقع')->required()->maxLength(120),
                                    TextInput::make('tagline.ar')->label('الشعار')->maxLength(255),
                                    TextInput::make('address_line.ar')->label('العنوان')->maxLength(255),
                                ]),
                            ]),
                    ]),

                Section::make(__('shared.branding.contact'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('support_email')->email()->maxLength(191),
                        TextInput::make('support_phone')->tel()->maxLength(32),
                        TextInput::make('whatsapp_number')->tel()->maxLength(32),
                    ]),

                Section::make(__('shared.branding.social'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('social.instagram')->label('Instagram')->url()->prefix('https://')->dehydrateStateUsing(fn (?string $state): ?string => self::normalizeSocialUrl($state)),
                        TextInput::make('social.facebook')->label('Facebook')->url()->prefix('https://')->dehydrateStateUsing(fn (?string $state): ?string => self::normalizeSocialUrl($state)),
                        TextInput::make('social.tiktok')->label('TikTok')->url()->prefix('https://')->dehydrateStateUsing(fn (?string $state): ?string => self::normalizeSocialUrl($state)),
                        TextInput::make('social.x')->label('X (Twitter)')->url()->prefix('https://')->dehydrateStateUsing(fn (?string $state): ?string => self::normalizeSocialUrl($state)),
                        TextInput::make('social.youtube')->label('YouTube')->url()->prefix('https://')->dehydrateStateUsing(fn (?string $state): ?string => self::normalizeSocialUrl($state)),
                    ]),

                Section::make(__('shared.branding.assets'))
                    ->columns(2)
                    ->schema([
                        Group::make([
                            SpatieMediaLibraryFileUpload::make('logo_light')
                                ->collection('logo_light')->image()->label(__('shared.branding.logo_light')),
                            SpatieMediaLibraryFileUpload::make('logo_dark')
                                ->collection('logo_dark')->image()->label(__('shared.branding.logo_dark')),
                            SpatieMediaLibraryFileUpload::make('favicon')
                                ->collection('favicon')->image()->label(__('shared.branding.favicon')),
                        ]),
                        Group::make([
                            SpatieMediaLibraryFileUpload::make('og_image')
                                ->collection('og_image')->image()->label(__('shared.branding.og_image')),
                            SpatieMediaLibraryFileUpload::make('app_store_badge')
                                ->collection('app_store_badge')->image()->label(__('shared.branding.app_store_badge')),
                            SpatieMediaLibraryFileUpload::make('play_store_badge')
                                ->collection('play_store_badge')->image()->label(__('shared.branding.play_store_badge')),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        app(SaveBrandingAction::class)->execute($data);

        Notification::make()
            ->title(__('shared.branding.saved'))
            ->success()
            ->send();
    }

    private static function normalizeSocialUrl(?string $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        return preg_match('/^https?:\/\//i', $state) === 1 ? $state : 'https://'.$state;
    }
}

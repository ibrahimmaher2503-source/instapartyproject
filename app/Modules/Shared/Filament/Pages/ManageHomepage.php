<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Pages;

use App\Modules\Shared\Domain\Models\HomepageSettings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageHomepage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = 25;

    protected static string $view = 'filament.pages.manage-homepage';

    public ?array $data = [];

    public ?HomepageSettings $record = null;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.appearance');
    }

    public static function getNavigationLabel(): string
    {
        return __('shared::shared.homepage.nav_label');
    }

    public function getTitle(): string
    {
        return __('shared::shared.homepage.title');
    }

    public function mount(): void
    {
        $this->record = HomepageSettings::current();
        $this->form->fill([]);
    }

    public function form(Form $schema): Form
    {
        return $schema
            ->model($this->record)
            ->statePath('data')
            ->components([
                Section::make(__('shared::shared.homepage.media_section'))
                    ->description(__('shared::shared.homepage.media_section_description'))
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('hero_image')
                            ->collection('hero')
                            ->image()
                            ->maxFiles(1)
                            ->label(__('shared::shared.homepage.hero_image_label')),
                    ]),
            ]);
    }

    public function save(): void
    {
        $this->form->getState();

        Notification::make()
            ->title(__('shared::shared.homepage.saved'))
            ->success()
            ->send();
    }
}

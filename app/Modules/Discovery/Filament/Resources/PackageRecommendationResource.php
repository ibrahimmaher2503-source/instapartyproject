<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Filament\Resources;

use App\Modules\Discovery\Application\Actions\SavePackageRecommendationAction;
use App\Modules\Discovery\Domain\Models\PackageRecommendation;
use App\Modules\Discovery\Filament\Resources\PackageRecommendationResource\Pages\CreatePackageRecommendation;
use App\Modules\Discovery\Filament\Resources\PackageRecommendationResource\Pages\EditPackageRecommendation;
use App\Modules\Discovery\Filament\Resources\PackageRecommendationResource\Pages\ListPackageRecommendations;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PackageRecommendationResource extends Resource
{
    protected static ?string $model = PackageRecommendation::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.appearance');
    }

    public static function getNavigationLabel(): string
    {
        return __('discovery::discovery.package_recommendations.nav');
    }

    public static function getModelLabel(): string
    {
        return __('discovery::discovery.package_recommendations.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('discovery::discovery.package_recommendations.plural');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('discovery::discovery.package_recommendations.content'))
                ->description(__('discovery::discovery.package_recommendations.content_help'))
                ->schema([
                    Tabs::make('translations')->tabs([
                        Tab::make('English')->schema([
                            TextInput::make('name.en')->required()->maxLength(160)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (?string $state, $set) => $set('slug', Str::slug((string) $state))),
                            Textarea::make('description.en')->required()->rows(3)->maxLength(400),
                        ]),
                        Tab::make('العربية')->schema([
                            TextInput::make('name.ar')->required()->maxLength(160),
                            Textarea::make('description.ar')->required()->rows(3)->maxLength(400),
                        ]),
                    ])->columnSpanFull(),
                    TextInput::make('slug')->required()->unique(ignoreRecord: true)->maxLength(120),
                    Select::make('occasion_id')
                        ->label(__('discovery::discovery.package_recommendations.occasion'))
                        ->options(fn (): array => DB::table('occasions')->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')->map(fn (string $name): string => (string) data_get(json_decode($name, true), app()->getLocale(), data_get(json_decode($name, true), 'en')))->all())
                        ->searchable()
                        ->preload(),
                ])->columns(2),

            Section::make(__('discovery::discovery.package_recommendations.presentation'))
                ->schema([
                    SpatieMediaLibraryFileUpload::make('hero')
                        ->collection('hero')->image()->imageEditor()
                        ->maxSize(3072)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->label(__('discovery::discovery.package_recommendations.hero')),
                    TextInput::make('display_order')->numeric()->minValue(0)->default(0),
                    Toggle::make('is_published')->default(false)
                        ->label(__('discovery::discovery.package_recommendations.published')),
                ])->columns(3),

            Section::make(__('discovery::discovery.package_recommendations.budget'))
                ->description(__('discovery::discovery.package_recommendations.budget_help'))
                ->schema([
                    TextInput::make('min_budget_minor')->numeric()->minValue(0)->suffix('pt'),
                    TextInput::make('max_budget_minor')->numeric()->minValue(0)->gte('min_budget_minor')->suffix('pt'),
                    Select::make('budget_currency')->options(['EGP' => 'EGP'])->default('EGP'),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('display_order')
            ->defaultSort('display_order')
            ->columns([
                SpatieMediaLibraryImageColumn::make('hero')->collection('hero')->conversion('thumb')->circular(),
                TextColumn::make('name')->searchable()->limit(45),
                TextColumn::make('slug')->toggleable(),
                IconColumn::make('is_published')->boolean(),
                TextColumn::make('display_order')->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([TernaryFilter::make('is_published')])
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function persistViaAction(array $data, ?PackageRecommendation $record): Model
    {
        return app(SavePackageRecommendationAction::class)->execute($record, $data);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPackageRecommendations::route('/'),
            'create' => CreatePackageRecommendation::route('/create'),
            'edit' => EditPackageRecommendation::route('/{record}/edit'),
        ];
    }
}

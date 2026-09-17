<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Filament\Resources\UserResource\Pages\ListUsers;
use App\Modules\Identity\Filament\Resources\UserResource\Pages\ViewUser;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.security');
    }

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('identity.nav.users');
    }

    public static function getModelLabel(): string
    {
        return __('identity.models.user.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity.models.user.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('identity.columns.id'))
                    ->copyable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('identity.columns.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('identity.columns.email'))
                    ->searchable(),
                TextColumn::make('phone_e164')
                    ->label(__('identity.columns.phone'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('identity.columns.status'))
                    ->badge(),
                TextColumn::make('roles.name')
                    ->label(__('identity.columns.role'))
                    ->badge()
                    ->listWithLineBreaks()
                    ->formatStateUsing(function (?string $state): string {
                        if ($state === null || $state === '') {
                            return '';
                        }
                        $key = 'identity.roles.'.str_replace('.', '_', $state);
                        $translated = __($key);

                        return $translated === $key ? Str::headline(str_replace('.', ' ', $state)) : $translated;
                    }),
                TextColumn::make('created_at')
                    ->label(__('identity.columns.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $schema): Infolist
    {
        return $schema->components([
            Section::make(__('identity.models.user.singular'))
                ->schema([
                    TextEntry::make('name')->label(__('identity.columns.name')),
                    TextEntry::make('email')->label(__('identity.columns.email'))->copyable(),
                    TextEntry::make('phone_e164')->label(__('identity.columns.phone')),
                    TextEntry::make('status')->label(__('identity.columns.status'))->badge(),
                    TextEntry::make('roles.name')
                        ->label(__('identity.columns.role'))
                        ->badge()
                        ->listWithLineBreaks()
                        ->formatStateUsing(function (?string $state): string {
                            if ($state === null || $state === '') {
                                return '';
                            }
                            $key = 'identity.roles.'.str_replace('.', '_', $state);
                            $translated = __($key);

                            return $translated === $key ? Str::headline(str_replace('.', ' ', $state)) : $translated;
                        }),
                    TextEntry::make('created_at')->label(__('identity.columns.created_at'))->dateTime(),
                ])
                ->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }
}

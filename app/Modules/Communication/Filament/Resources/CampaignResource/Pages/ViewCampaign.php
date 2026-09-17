<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\CampaignResource\Pages;

use App\Modules\Communication\Domain\Enums\CampaignRecipientStatus;
use App\Modules\Communication\Domain\Models\CampaignRecipient;
use App\Modules\Communication\Domain\Models\CampaignRun;
use App\Modules\Communication\Filament\Resources\CampaignResource;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ViewCampaign extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CampaignResource::class;

    public function infolist(Infolist $schema): Infolist
    {
        return $schema->components([
            Section::make(__('communication.view_campaign.section_details'))
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('name')->label(__('communication.view_campaign.field_name')),
                        TextEntry::make('channel')
                            ->label(__('communication.view_campaign.field_channel'))
                            ->formatStateUsing(fn ($state) => $state->label()),
                        TextEntry::make('status')
                            ->label(__('communication.view_campaign.field_status'))
                            ->badge()
                            ->color(fn ($state) => $state->color())
                            ->formatStateUsing(fn ($state) => $state->label()),
                    ]),
                ]),

            Section::make(__('communication.view_campaign.section_run_stats'))
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('latestRun.recipients_total')
                            ->label(__('communication.view_campaign.field_total_recipients'))
                            ->default('—'),
                        TextEntry::make('latestRun.recipients_sent')
                            ->label(__('communication.view_campaign.field_sent'))
                            ->default('—'),
                        TextEntry::make('latestRun.recipients_failed')
                            ->label(__('communication.view_campaign.field_failed'))
                            ->default('—'),
                        TextEntry::make('skipped_count')
                            ->label(__('communication.view_campaign.field_skipped'))
                            ->state(function ($record) {
                                $run = $record->runs()->latest()->first();
                                if ($run === null) {
                                    return '—';
                                }

                                return $run->recipients_total - $run->recipients_sent - $run->recipients_failed;
                            }),
                    ]),
                ])
                ->visible(fn ($record) => $record->runs()->exists()),
        ]);
    }

    public function table(Table $table): Table
    {
        $campaign = $this->record;
        $run = CampaignRun::where('campaign_id', $campaign->id)->latest()->first();

        return $table
            ->query(
                $run
                    ? CampaignRecipient::query()->where('campaign_run_id', $run->id)->with(['user', 'dispatch'])
                    : CampaignRecipient::query()->whereRaw('1=0')
            )
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('communication.view_campaign.table_recipient'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('communication.view_campaign.table_status'))
                    ->badge()
                    ->color(fn (CampaignRecipientStatus $state) => $state->color())
                    ->formatStateUsing(fn (CampaignRecipientStatus $state) => ucfirst($state->value)),
                TextColumn::make('dispatch.locale')
                    ->label(__('communication.view_campaign.table_locale'))
                    ->default('—'),
                TextColumn::make('dispatch.provider')
                    ->label(__('communication.view_campaign.table_provider'))
                    ->default('—'),
                TextColumn::make('dispatch.error_message')
                    ->label(__('communication.view_campaign.table_error'))
                    ->default('—')
                    ->limit(60),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(CampaignRecipientStatus::cases())->mapWithKeys(fn ($c) => [$c->value => ucfirst($c->value)])),
            ])
            ->heading(__('communication.view_campaign.table_heading_recipients'))
            ->paginated([20, 50]);
    }
}

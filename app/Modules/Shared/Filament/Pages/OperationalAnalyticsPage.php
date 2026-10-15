<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Pages;

use App\Modules\Shared\Application\Services\OperationalAnalyticsService;
use Filament\Pages\Page;

class OperationalAnalyticsPage extends Page
{
    protected static string $view = 'filament.pages.operational-analytics';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    public int $days = 30;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_operational_analytics') === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.operations');
    }

    public static function getNavigationLabel(): string
    {
        return __('shared::operations.analytics');
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    protected function getViewData(): array
    {
        abort_unless(self::canAccess(), 403);
        $this->validate(['days' => ['integer', 'in:7,30,90']]);

        return ['periods' => app(OperationalAnalyticsService::class)->summarize($this->days)];
    }
}

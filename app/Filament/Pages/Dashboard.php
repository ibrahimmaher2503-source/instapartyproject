<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Modules\Shared\Filament\Widgets\AdminDashboardSectionsWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    public function getTitle(): string|Htmlable
    {
        return __('admin.dashboard.title');
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.dashboard.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.dashboard.title');
    }

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 6,
            'xl' => 12,
        ];
    }

    public function getWidgets(): array
    {
        return [AdminDashboardSectionsWidget::class];
    }
}

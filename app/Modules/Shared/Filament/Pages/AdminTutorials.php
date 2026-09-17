<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Pages;

use App\Modules\Shared\Application\Services\TutorialManifestService;
use Filament\Pages\Page;

final class AdminTutorials extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'tutorials';

    protected static string $view = 'filament.pages.admin-tutorials';

    public array $flows = [];

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_tutorial.page.navigation');
    }

    public function getTitle(): string
    {
        return __('admin_tutorial.page.title');
    }

    public function mount(): void
    {
        $service = new TutorialManifestService(
            flowsPath: base_path('docs/admin-flows'),
            screenshotsPath: base_path('docs/admin-flows/screenshots'),
        );

        $this->flows = array_map(function (array $flow): array {
            $flow['steps'] = array_map(function (array $step): array {
                foreach (['screenshot_en', 'screenshot_ar'] as $key) {
                    if ($step[$key] !== null) {
                        $step[$key] = asset('admin-tutorial/screenshots/'.$step[$key]);
                    }
                }

                return $step;
            }, $flow['steps']);

            return $flow;
        }, $service->loadForViewer(auth()->user())['flows']);
    }
}

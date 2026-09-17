<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\RenderHooks;

use App\Modules\Shared\Application\Services\TutorialManifestService;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

final class TutorialLauncherRenderHook
{
    public static function register(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::TOPBAR_END,
            fn (): string => self::render(),
        );
    }

    private static function render(): string
    {
        $service = new TutorialManifestService(
            flowsPath: base_path('docs/admin-flows'),
            screenshotsPath: base_path('docs/admin-flows/screenshots'),
        );

        $manifest = $service->loadForViewer(auth()->user());

        return Blade::render(
            '<x-shared.tutorial-launcher :flows="$flows" :manifest-exists="$manifestExists" />',
            [
                'flows' => $manifest['flows'],
                // Lets the overlay distinguish "never generated" from
                // "permissions filtered everything out".
                'manifestExists' => $service->manifestExists(),
            ],
        );
    }
}

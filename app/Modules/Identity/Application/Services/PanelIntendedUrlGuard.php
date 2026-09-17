<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use Filament\Panel;
use Illuminate\Http\Request;

final class PanelIntendedUrlGuard
{
    public function forgetWhenOutsidePanel(Request $request, ?Panel $panel): void
    {
        $session = $request->hasSession() ? $request->session() : session();
        $intendedUrl = $session->get('url.intended');

        if ($panel === null) {
            $session->forget('url.intended');

            return;
        }

        if (! is_string($intendedUrl) || $intendedUrl === '') {
            return;
        }

        $parts = parse_url($intendedUrl);

        if ($parts === false) {
            $session->forget('url.intended');

            return;
        }

        $host = $parts['host'] ?? null;
        $path = '/'.trim((string) ($parts['path'] ?? ''), '/');
        $panelPath = '/'.trim($panel->getPath(), '/');
        $belongsToCurrentPanel = ($host === null || strcasecmp($host, $request->getHost()) === 0)
            && ($path === $panelPath || str_starts_with($path, $panelPath.'/'));

        if (! $belongsToCurrentPanel) {
            $session->forget('url.intended');
        }
    }
}

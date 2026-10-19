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
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : null;
        $port = $parts['port'] ?? match ($scheme) {
            'http' => 80,
            'https' => 443,
            default => null,
        };
        $sameOrigin = $host === null
            ? $scheme === null && ! isset($parts['port'])
            : strcasecmp($host, $request->getHost()) === 0
                && in_array($scheme, [null, strtolower($request->getScheme())], true)
                && ($port === null || $port === $request->getPort())
                && ! isset($parts['user'])
                && ! isset($parts['pass']);
        $belongsToCurrentPanel = $sameOrigin
            && ($path === $panelPath || str_starts_with($path, $panelPath.'/'));

        if (! $belongsToCurrentPanel) {
            $session->forget('url.intended');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Contracts;

use App\Modules\Shared\Domain\Exceptions\SvgSecurityViolation;

/**
 * Strips `<script>`, event handlers, external entity references, foreignObject,
 * and inline JavaScript URLs from SVG bytes before persisting.
 *
 * Bound to `EnshrinedSvgSanitizer` in `SharedServiceProvider::register()`.
 *
 * Per FR-EXT-MED-005 and ADR-0047 §3.
 */
interface SvgSanitizer
{
    /**
     * @throws SvgSecurityViolation when the bytes cannot be safely sanitized
     */
    public function sanitize(string $svgBytes): string;
}

<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Services;

use App\Modules\Shared\Domain\Contracts\SvgSanitizer;
use App\Modules\Shared\Domain\Exceptions\SvgSecurityViolation;
use enshrined\svgSanitize\Sanitizer;

/**
 * `enshrined/svg-sanitize` wrapper.
 *
 * Removes `<script>`, event handler attributes, external entity references,
 * `<foreignObject>`, and `javascript:` URLs. Returns sanitized SVG bytes;
 * caller re-streams those bytes into media-library (never the original).
 *
 * Per FR-EXT-MED-005 and ADR-0047 §6.7.
 */
final class EnshrinedSvgSanitizer implements SvgSanitizer
{
    public function __construct(private readonly Sanitizer $sanitizer = new Sanitizer)
    {
        $this->sanitizer->removeRemoteReferences(true);
        $this->sanitizer->minify(false);
    }

    public function sanitize(string $svgBytes): string
    {
        $cleaned = $this->sanitizer->sanitize($svgBytes);

        if ($cleaned === false || $cleaned === '') {
            throw SvgSecurityViolation::unsanitizable();
        }

        return $cleaned;
    }
}

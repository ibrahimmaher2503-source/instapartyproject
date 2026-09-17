<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Exceptions;

use RuntimeException;

/**
 * Raised when `SvgSanitizer::sanitize()` cannot safely clean the input SVG.
 *
 * The HTTP layer maps this to 422 with `errors[0].code = "media.svg_security_violation"`.
 */
class SvgSecurityViolation extends RuntimeException
{
    public static function unsanitizable(): self
    {
        return new self('SVG could not be safely sanitized.');
    }
}

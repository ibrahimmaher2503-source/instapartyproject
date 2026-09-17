<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Raised by `CdnCacheBuster::purge()` when the CDN purge API call fails.
 *
 * The HTTP layer maps this to 503 with `errors[0].code = "cdn.bust_failed"`;
 * the action is naturally retryable.
 */
class CdnCacheBustFailed extends RuntimeException
{
    /**
     * @param  array<string>  $paths
     */
    public static function forPaths(array $paths, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('CDN cache bust failed for %d path(s).', count($paths)),
            previous: $previous,
        );
    }
}

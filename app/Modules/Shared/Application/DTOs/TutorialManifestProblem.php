<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\DTOs;

/**
 * A single validation failure found while parsing the admin flow catalogue.
 *
 * The manifest build fails loudly rather than emitting a partial manifest — see
 * docs/admin-tutorial-build-prompt.md §7.1. Drift between the .md catalogue and
 * the shipped screenshots/lang keys must surface at build time, not to a user
 * staring at a broken tutorial step.
 */
final readonly class TutorialManifestProblem
{
    public function __construct(
        public string $file,
        public string $message,
    ) {}

    public function toString(): string
    {
        return "{$this->file}: {$this->message}";
    }
}

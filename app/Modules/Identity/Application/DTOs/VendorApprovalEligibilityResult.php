<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\DTOs;

final readonly class VendorApprovalEligibilityResult
{
    /**
     * @param  array<string, bool>  $checks
     * @param  array<string, string>  $errors
     */
    public function __construct(
        public bool $eligible,
        public array $checks,
        public array $errors,
    ) {}

    public function passes(string $check): bool
    {
        return $this->checks[$check] ?? false;
    }
}

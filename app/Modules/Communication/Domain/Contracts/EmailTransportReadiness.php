<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Contracts;

interface EmailTransportReadiness
{
    public function isConfigured(): bool;
}

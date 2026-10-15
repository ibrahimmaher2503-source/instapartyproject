<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

final readonly class ServicePackageReadDTO
{
    /**
     * @param  array{en: string, ar: string}  $name
     * @param  array{en: string, ar: string}  $description
     * @param  list<array{public_id: string, name: array{en: string, ar: string}, description: array{en: string, ar: string}, quantity: int, sort_order: int}>  $components
     * @param  list<array{public_id: string, thumb: string, medium: string, original: string}>  $media
     */
    public function __construct(
        public string $publicId,
        public string $code,
        public array $name,
        public array $description,
        public int $priceMinor,
        public string $currency,
        public array $components,
        public array $media,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return [
            'public_id' => $this->publicId,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'price_minor' => $this->priceMinor,
            'currency' => $this->currency,
            'components' => $this->components,
            'media' => $this->media,
        ];
    }
}

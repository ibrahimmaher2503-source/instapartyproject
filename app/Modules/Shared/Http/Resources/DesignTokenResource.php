<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignTokenResource extends JsonResource
{
    /**
     * @param  array{public_id: ?string, name: string, tokens: array, updated_at: ?string}  $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->resource['public_id'],
            'name' => $this->resource['name'],
            'tokens' => $this->normalizeTokens($this->resource['tokens']),
            'updated_at' => $this->resource['updated_at'],
        ];
    }

    /**
     * PHP coerces array keys like '50' to int 50, then Filament's Repeater/Builder
     * normalization can return them as sequential arrays. Re-key the four palettes
     * back to the canonical Tailwind shade scale so the frontend can read them as
     * an object with string shade keys.
     *
     * @param  array<string, mixed>  $tokens
     * @return array<string, mixed>
     */
    private function normalizeTokens(array $tokens): array
    {
        $shades = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900'];

        foreach (['primary', 'secondary', 'accent', 'neutral'] as $palette) {
            $values = $tokens['colors'][$palette] ?? null;
            if (! is_array($values)) {
                continue;
            }
            $rekeyed = [];
            $i = 0;
            foreach ($values as $key => $value) {
                $shade = is_string($key) && in_array($key, $shades, true)
                    ? $key
                    : ($shades[$i] ?? (string) $key);
                $rekeyed[$shade] = $value;
                $i++;
            }
            $tokens['colors'][$palette] = (object) $rekeyed;
        }

        return $tokens;
    }
}

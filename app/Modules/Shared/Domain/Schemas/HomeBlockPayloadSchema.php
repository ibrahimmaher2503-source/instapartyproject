<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Schemas;

use App\Modules\Shared\Domain\Enums\HomeBlockType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class HomeBlockPayloadSchema
{
    /** @var list<string> */
    private const HERO_SCENE_ASSET_KEYS = [
        'cake', 'pedestal', 'party_hat', 'gift',
        'balloon_navy', 'balloon_ivory', 'balloon_emerald',
        'ribbon_navy', 'ribbon_emerald', 'confetti_floor', 'decorative_confetti',
    ];

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function validate(HomeBlockType $type, array $payload): array
    {
        $rules = match ($type) {
            HomeBlockType::HeroCarousel => [
                'slides' => ['required', 'array', 'min:1', 'max:10'],
                'slides.*.image_url' => ['required', 'string', 'url', 'max:1024'],
                'slides.*.headline.en' => ['required', 'string', 'max:160'],
                'slides.*.headline.ar' => ['required', 'string', 'max:160'],
                'slides.*.eyebrow.en' => ['nullable', 'string', 'max:120'],
                'slides.*.eyebrow.ar' => ['nullable', 'string', 'max:120'],
                'slides.*.headline_highlight.en' => ['nullable', 'string', 'max:160'],
                'slides.*.headline_highlight.ar' => ['nullable', 'string', 'max:160'],
                'slides.*.sub.en' => ['nullable', 'string', 'max:240'],
                'slides.*.sub.ar' => ['nullable', 'string', 'max:240'],
                'slides.*.cta_label.en' => ['nullable', 'string', 'max:60'],
                'slides.*.cta_label.ar' => ['nullable', 'string', 'max:60'],
                'slides.*.cta_url' => ['nullable', 'string', 'max:512'],
                'slides.*.image_alt.en' => ['nullable', 'string', 'max:240'],
                'slides.*.image_alt.ar' => ['nullable', 'string', 'max:240'],
                'slides.*.scene_assets' => ['nullable', 'array', 'max:11'],
                'slides.*.scene_assets.*' => ['array'],
                'slides.*.scene_assets.*.key' => ['required', 'string', 'in:'.implode(',', self::HERO_SCENE_ASSET_KEYS)],
                'slides.*.scene_assets.*.url' => ['required', 'string', 'url', 'max:1024'],
                'slides.*.scene_assets.*.alt.en' => ['nullable', 'string', 'max:240'],
                'slides.*.scene_assets.*.alt.ar' => ['nullable', 'string', 'max:240'],
            ],
            HomeBlockType::FeaturedServices => [
                'title.en' => ['required', 'string', 'max:120'],
                'title.ar' => ['required', 'string', 'max:120'],
                'service_public_ids' => ['required', 'array', 'min:1', 'max:24'],
                'service_public_ids.*' => ['string', 'size:26'],
            ],
            HomeBlockType::FeaturedOccasions, HomeBlockType::FeaturedCategories => [
                'title.en' => ['required', 'string', 'max:120'],
                'title.ar' => ['required', 'string', 'max:120'],
                'public_ids' => ['required', 'array', 'min:1', 'max:24'],
                'public_ids.*' => ['string', 'size:26'],
            ],
            HomeBlockType::VendorSpotlight => [
                'title.en' => ['required', 'string', 'max:120'],
                'title.ar' => ['required', 'string', 'max:120'],
                'vendor_public_id' => ['required', 'string', 'size:26'],
            ],
            HomeBlockType::VendorJoin => [
                'headline.en' => ['required', 'string', 'max:160'],
                'headline.ar' => ['required', 'string', 'max:160'],
                'body.en' => ['required', 'string', 'max:600'],
                'body.ar' => ['required', 'string', 'max:600'],
                'image_url' => ['nullable', 'string', 'url', 'max:1024'],
                'cta_label.en' => ['required', 'string', 'max:60'],
                'cta_label.ar' => ['required', 'string', 'max:60'],
                'cta_url' => ['required', 'string', 'max:512'],
            ],
            HomeBlockType::CtaBanner => [
                'headline.en' => ['required', 'string', 'max:160'],
                'headline.ar' => ['required', 'string', 'max:160'],
                'image_url' => ['nullable', 'string', 'url', 'max:1024'],
                'cta_label.en' => ['required', 'string', 'max:60'],
                'cta_label.ar' => ['required', 'string', 'max:60'],
                'cta_url' => ['required', 'string', 'max:512'],
            ],
            HomeBlockType::TextImageSplit => [
                'headline.en' => ['required', 'string', 'max:160'],
                'headline.ar' => ['required', 'string', 'max:160'],
                'body.en' => ['required', 'string', 'max:2000'],
                'body.ar' => ['required', 'string', 'max:2000'],
                'image_url' => ['required', 'string', 'url', 'max:1024'],
                'image_side' => ['required', 'string', 'in:left,right'],
            ],
            HomeBlockType::Testimonials => [
                'title.en' => ['required', 'string', 'max:120'],
                'title.ar' => ['required', 'string', 'max:120'],
                'items' => ['required', 'array', 'min:1', 'max:12'],
                'items.*.quote.en' => ['required', 'string', 'max:600'],
                'items.*.quote.ar' => ['required', 'string', 'max:600'],
                'items.*.author' => ['required', 'string', 'max:120'],
                'items.*.avatar_url' => ['nullable', 'string', 'url', 'max:1024'],
            ],
            HomeBlockType::LoyaltyPromo => [
                'headline.en' => ['required', 'string', 'max:160'],
                'headline.ar' => ['required', 'string', 'max:160'],
                'body.en' => ['nullable', 'string', 'max:600'],
                'body.ar' => ['nullable', 'string', 'max:600'],
                'cta_url' => ['nullable', 'string', 'max:512'],
            ],
        };

        $validator = Validator::make($payload, $rules);
        if ($type === HomeBlockType::HeroCarousel) {
            $validator->after(function ($validator) use ($payload): void {
                $allowedSlideKeys = [
                    'image_url', 'image_alt', 'eyebrow', 'headline', 'headline_highlight',
                    'sub', 'cta_label', 'cta_url', 'scene_assets',
                ];

                foreach ((array) ($payload['slides'] ?? []) as $slideIndex => $slide) {
                    foreach (array_keys((array) $slide) as $key) {
                        if (! in_array($key, $allowedSlideKeys, true)) {
                            $validator->errors()->add("slides.{$slideIndex}.{$key}", 'This hero field is not supported.');
                        }
                    }

                    $assetKeys = [];
                    foreach ((array) ($slide['scene_assets'] ?? []) as $assetIndex => $asset) {
                        foreach (array_keys((array) $asset) as $key) {
                            if (! in_array($key, ['key', 'url', 'alt'], true)) {
                                $validator->errors()->add("slides.{$slideIndex}.scene_assets.{$assetIndex}.{$key}", 'This scene asset field is not supported.');
                            }
                        }

                        if (isset($asset['key']) && in_array($asset['key'], $assetKeys, true)) {
                            $validator->errors()->add("slides.{$slideIndex}.scene_assets.{$assetIndex}.key", 'Scene asset keys must be unique.');
                        }
                        if (isset($asset['key'])) {
                            $assetKeys[] = $asset['key'];
                        }
                    }
                }
            });
        }
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $validator->validated();
    }
}

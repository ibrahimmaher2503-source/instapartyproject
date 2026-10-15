<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Domain\Enums\CmsSlug;
use App\Modules\Shared\Domain\Enums\NavigationSlot;
use App\Modules\Shared\Domain\Events\PublicThemeChanged;
use App\Modules\Shared\Domain\Models\CmsPage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateCmsPageAction
{
    /** @param array<string, mixed> $data */
    public function execute(CmsPage $page, array $data): CmsPage
    {
        return DB::transaction(function () use ($page, $data): CmsPage {
            $record = CmsPage::query()->lockForUpdate()->findOrFail($page->getKey());
            $publicSlug = $this->normalizeSlug($data['public_slug'] ?? null);
            $this->validatePublicSlug($record, $publicSlug);
            $slugChanged = $record->public_slug !== $publicSlug;

            $record->fill(Arr::only($data, ['title', 'body', 'meta_description', 'blocks']));
            $record->public_slug = $publicSlug;
            $record->updated_by = Auth::id();
            $record->save();

            if ($slugChanged) {
                DB::afterCommit(function () use ($record): void {
                    foreach (NavigationSlot::cases() as $slot) {
                        foreach (['en', 'ar'] as $locale) {
                            Cache::forget('theme:menus:'.$slot->value.':'.$locale);
                        }
                    }

                    event(new PublicThemeChanged(reason: 'cms_page.public_slug_changed', publicId: $record->public_id));
                });
            }

            return $record->fresh();
        });
    }

    private function normalizeSlug(mixed $slug): ?string
    {
        if (! is_string($slug) || trim($slug) === '') {
            return null;
        }

        return strtolower(trim($slug));
    }

    private function validatePublicSlug(CmsPage $page, ?string $publicSlug): void
    {
        if ($publicSlug === null) {
            return;
        }

        if (strlen($publicSlug) > 120 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $publicSlug) !== 1) {
            throw ValidationException::withMessages([
                'public_slug' => [__('shared::shared.cms.invalid_public_slug')],
            ]);
        }

        $canonicalSlugs = array_column(CmsSlug::cases(), 'value');
        if (in_array($publicSlug, ['faq', 'trust'], true)
            || (in_array($publicSlug, $canonicalSlugs, true) && $publicSlug !== $page->slug->value)
        ) {
            throw ValidationException::withMessages([
                'public_slug' => [__('shared::shared.cms.reserved_public_slug')],
            ]);
        }

        $collision = CmsPage::query()
            ->where('id', '!=', $page->getKey())
            ->where(function ($query) use ($publicSlug): void {
                $query->where('public_slug', $publicSlug)->orWhere('slug', $publicSlug);
            })
            ->exists();

        if ($collision) {
            throw ValidationException::withMessages([
                'public_slug' => [__('shared::shared.cms.duplicate_public_slug')],
            ]);
        }
    }
}

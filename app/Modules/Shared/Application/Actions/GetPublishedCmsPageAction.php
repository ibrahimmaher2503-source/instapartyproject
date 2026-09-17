<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Domain\Models\CmsPage;

class GetPublishedCmsPageAction
{
    public function execute(string $slug): ?CmsPage
    {
        return CmsPage::query()->published()->where('slug', $slug)->first();
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources\CmsPageResource\Pages;

use App\Modules\Shared\Application\Actions\UpdateCmsPageAction;
use App\Modules\Shared\Domain\Models\CmsPage;
use App\Modules\Shared\Filament\Resources\CmsPageResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCmsPage extends EditRecord
{
    protected static string $resource = CmsPageResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var CmsPage $record */
        return app(UpdateCmsPageAction::class)->execute($record, $data);
    }
}

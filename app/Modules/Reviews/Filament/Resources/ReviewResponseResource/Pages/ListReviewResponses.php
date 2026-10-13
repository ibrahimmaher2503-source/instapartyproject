<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Filament\Resources\ReviewResponseResource\Pages;

use App\Modules\Reviews\Filament\Resources\ReviewResponseResource;
use Filament\Resources\Pages\ListRecords;

final class ListReviewResponses extends ListRecords
{
    protected static string $resource = ReviewResponseResource::class;
}

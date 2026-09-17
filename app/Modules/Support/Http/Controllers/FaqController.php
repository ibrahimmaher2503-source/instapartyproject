<?php

declare(strict_types=1);

namespace App\Modules\Support\Http\Controllers;

use App\Modules\Shared\Http\ApiResponse;
use App\Modules\Support\Http\Resources\FaqCategoryResource;
use App\Modules\Support\Http\Resources\FaqItemResource;
use App\Modules\Support\Infrastructure\Repositories\EloquentFaqRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

/**
 * @group Support
 */
class FaqController extends Controller
{
    public function __construct(
        private readonly EloquentFaqRepository $repository,
    ) {}

    public function categories(): JsonResponse
    {
        $categories = Cache::remember('faq:categories', 300, fn () => $this->repository->getAllActiveCategories());

        return ApiResponse::success(FaqCategoryResource::collection($categories));
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        $results = $this->repository->search($request->string('q')->toString());

        return ApiResponse::success(FaqItemResource::collection($results));
    }
}

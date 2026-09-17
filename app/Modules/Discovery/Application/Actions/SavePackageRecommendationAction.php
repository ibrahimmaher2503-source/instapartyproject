<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Application\Actions;

use App\Modules\Discovery\Domain\Models\PackageRecommendation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class SavePackageRecommendationAction
{
    /** @param array<string, mixed> $data */
    public function execute(?PackageRecommendation $package, array $data): PackageRecommendation
    {
        return DB::transaction(function () use ($package, $data): PackageRecommendation {
            $package ??= new PackageRecommendation;
            $package->fill([
                ...$data,
                'created_by' => $package->exists ? $package->getAttribute('created_by') : Auth::id(),
                'updated_by' => Auth::id(),
            ])->save();

            $package->refresh();

            return $package;
        });
    }
}

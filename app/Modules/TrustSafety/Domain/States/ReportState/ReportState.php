<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Domain\States\ReportState;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class ReportState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(OpenState::class)
            ->allowTransition(OpenState::class, UnderReviewState::class)
            ->allowTransition(UnderReviewState::class, ResolvedState::class)
            ->allowTransition(UnderReviewState::class, DismissedState::class);
    }
}

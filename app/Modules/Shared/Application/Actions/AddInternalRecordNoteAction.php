<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Domain\Models\InternalRecordNote;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class AddInternalRecordNoteAction
{
    public static function authorizeSubject(Model $subject, Authenticatable $actor): void
    {
        if (! in_array($subject::class, InternalRecordNote::allowedSubjectClasses(), true)) {
            throw new InvalidArgumentException('Unsupported internal note subject.');
        }

        if ($subject::class === 'App\Modules\Identity\Domain\Models\User' && (! method_exists($subject, 'hasRole') || ! $subject->hasRole('customer'))) {
            throw new InvalidArgumentException('Internal notes on users are limited to customer accounts.');
        }

        if (filament()->getCurrentPanel()?->getId() !== 'admin') {
            throw new AuthorizationException('Internal notes are only available in the admin panel.');
        }

        Gate::forUser($actor)->authorize('view', $subject);
        Gate::forUser($actor)->authorize('update', $subject);
    }

    public function execute(Model $subject, Authenticatable $actor, string $kind, string $body): InternalRecordNote
    {
        self::authorizeSubject($subject, $actor);

        $body = trim($body);
        if (! in_array($kind, ['note', 'contact'], true) || $body === '' || mb_strlen($body) > 4000) {
            throw new InvalidArgumentException('Note kind or body is invalid.');
        }

        return DB::transaction(fn (): InternalRecordNote => $subject->internalNotes()->create([
            'author_id' => $actor->getAuthIdentifier(),
            'kind' => $kind,
            'body' => $body,
        ]));
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\Tags\Tag;

class SyncInternalRecordTagsAction
{
    /** @param array<int, array{en?: string, ar?: string}> $tags */
    public function execute(Model $subject, Authenticatable $actor, array $tags): void
    {
        AddInternalRecordNoteAction::authorizeSubject($subject, $actor);
        if (count($tags) > 20) {
            throw new InvalidArgumentException('At most 20 internal tags may be assigned.');
        }

        DB::transaction(function () use ($subject, $tags): void {
            $tagIds = collect($tags)->map(function (array $tag): int {
                $name = ['en' => trim((string) ($tag['en'] ?? '')), 'ar' => trim((string) ($tag['ar'] ?? ''))];
                if ($name['en'] === '' || $name['ar'] === '' || mb_strlen($name['en']) > 80 || mb_strlen($name['ar']) > 80) {
                    throw new InvalidArgumentException('Internal tags require English and Arabic labels up to 80 characters.');
                }

                $existing = Tag::query()->where('type', 'internal_record')->get()
                    ->first(fn (Tag $candidate): bool => $candidate->getTranslations('name') === $name);

                return ($existing ?? Tag::query()->create(['name' => $name, 'type' => 'internal_record']))->getKey();
            })->unique()->values()->all();

            $subject->syncTagIds($tagIds, 'internal_record');
        });
    }
}

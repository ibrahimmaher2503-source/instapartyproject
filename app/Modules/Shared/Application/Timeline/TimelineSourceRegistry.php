<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Timeline;

use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Application\Timeline\Exceptions\TimelineSubjectNotRegisteredException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class TimelineSourceRegistry
{
    /** @var array<class-string<Model>, array<int, SourceTableDescriptor>> keyed by canonicalRank */
    private array $descriptors = [];

    public function register(string $subjectClass, SourceTableDescriptor ...$descriptors): void
    {
        foreach ($descriptors as $descriptor) {
            $existing = $this->descriptors[$subjectClass] ?? [];

            // Guard duplicate canonicalRank per (subject, source_table)
            foreach ($existing as $registered) {
                if ($registered->canonicalRank === $descriptor->canonicalRank
                    && $registered->sourceTable === $descriptor->sourceTable) {
                    throw new InvalidArgumentException(
                        "Duplicate canonicalRank [{$descriptor->canonicalRank}] for source_table [{$descriptor->sourceTable}] on subject [{$subjectClass}]. Each (subject, source_table) pair must have a unique rank."
                    );
                }
            }

            $existing[] = $descriptor;
            usort($existing, static fn (SourceTableDescriptor $a, SourceTableDescriptor $b) => $a->canonicalRank <=> $b->canonicalRank);

            $this->descriptors[$subjectClass] = $existing;
        }
    }

    /**
     * @return array<int, SourceTableDescriptor>
     *
     * @throws TimelineSubjectNotRegisteredException
     */
    public function descriptorsFor(Model $subject, TimelineAudience $audience): array
    {
        $class = get_class($subject);

        if (! $this->isRegistered($class)) {
            throw TimelineSubjectNotRegisteredException::for($class);
        }

        return array_values(array_filter(
            $this->descriptors[$class],
            static function (SourceTableDescriptor $descriptor) use ($audience): bool {
                $rules = $descriptor->visibilityRules[$audience->value] ?? null;

                // descriptor not listed for this audience → omit
                if ($rules === null) {
                    return false;
                }

                // adminOnly flag → omit for non-admin audiences
                if (($rules['adminOnly'] ?? false) && $audience !== TimelineAudience::Admin) {
                    return false;
                }

                return true;
            }
        ));
    }

    public function isRegistered(string $subjectClass): bool
    {
        return isset($this->descriptors[$subjectClass]);
    }

    /** @return list<class-string<Model>> */
    public function registeredSubjects(): array
    {
        return array_keys($this->descriptors);
    }
}

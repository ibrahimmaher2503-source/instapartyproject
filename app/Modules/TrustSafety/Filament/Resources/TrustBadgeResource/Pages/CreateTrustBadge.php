<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Filament\Resources\TrustBadgeResource\Pages;

use App\Modules\Settlement\Domain\Exceptions\DuplicateIdempotencyKeyWithDifferentPayloadException;
use App\Modules\Shared\Application\Services\IdempotencyService;
use App\Modules\TrustSafety\Filament\Resources\TrustBadgeResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

class CreateTrustBadge extends CreateRecord
{
    protected static string $resource = TrustBadgeResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    #[Locked]
    #[Url(as: 'submission', keep: true)]
    public string $submissionId = '';

    public function mount(): void
    {
        parent::mount();
        if (! Str::isUuid($this->submissionId)) {
            $this->submissionId = (string) Str::uuid();
        }
    }

    public function create(bool $another = false): void
    {
        parent::create($another);

        if ($another) {
            $this->submissionId = (string) Str::uuid();
        }
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()
            ->livewireTarget('createAnother')
            ->livewireClickHandlerEnabled(false)
            ->extraAttributes(['wire:click.throttle.500ms' => 'createAnother']);
    }

    protected function getCreatedNotification(): ?Notification
    {
        $this->dispatch('trust-badge-save-confirmed');

        return parent::getCreatedNotification();
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $id = app(IdempotencyService::class)->remember(
                'internal_action',
                'trust-badge.create:'.auth()->id().':'.$this->submissionId,
                $data,
                fn (): int => (int) parent::handleRecordCreation($data)->getKey(),
            );
        } catch (DuplicateIdempotencyKeyWithDifferentPayloadException) {
            Notification::make()->danger()->title(__('trust-safety::trust-safety.badges.submission_conflict'))->send();
            throw (new Halt)->rollBackDatabaseTransaction();
        }

        return static::getModel()::query()->findOrFail($id);
    }
}

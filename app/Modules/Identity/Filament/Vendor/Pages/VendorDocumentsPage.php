<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Vendor\Pages;

use App\Modules\Identity\Application\Actions\DeleteVendorDocumentAction;
use App\Modules\Identity\Application\Actions\GenerateDocumentSignedUrlAction;
use App\Modules\Identity\Application\Actions\ReuploadVendorDocumentAction;
use App\Modules\Identity\Application\Actions\UploadVendorDocumentAction;
use App\Modules\Identity\Application\Services\RequiredVendorDocumentTypesResolver;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VendorDocumentsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'profile';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'vendor-portal.pages.vendor-documents';

    public function mount(): void
    {
        $count = VendorDocument::query()->where('vendor_profile_id', $this->getVendorProfile()->id)
            ->latestPerType()->where('status', DocumentStatus::Approved)->expiringWithinDays(30)->count();
        if ($count > 0) {
            Notification::make()->title(__('identity.compliance.expiring_alert', ['count' => $count]))->warning()->send();
        }
    }

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.documents.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.documents.title');
    }

    protected function getViewData(): array
    {
        $profile = $this->getVendorProfile();
        $resolver = app(RequiredVendorDocumentTypesResolver::class);
        $requiredTypes = $resolver->forBusinessType($profile->business_type);
        $latest = $profile->documents()->whereIn('doc_type', $requiredTypes)->latest('created_at')->latest('id')->get()
            ->groupBy(fn (VendorDocument $document): string => $document->doc_type_key)
            ->map(fn ($documents) => $documents->first());

        return ['requiredDocuments' => collect($requiredTypes)->map(fn (string $type): array => [
            'type' => $resolver->label($type),
            'status' => $latest->has($type)
                ? __('identity.document_status.'.$latest->get($type)->status->value)
                : __('identity.compliance.required_missing'),
            'expires_at' => $latest->get($type)?->expires_at?->format('Y-m-d'),
        ])];
    }

    public function getSubheading(): ?string
    {
        $resolver = app(RequiredVendorDocumentTypesResolver::class);
        $required = $resolver->forBusinessType($this->getVendorProfile()->business_type);

        return $required === []
            ? __('identity::document_types.none_required')
            : __('identity::document_types.requirements', ['names' => implode('، ', array_map(fn (string $key): string => $resolver->label($key), $required))]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                VendorDocument::query()
                    ->where('vendor_profile_id', $this->getVendorProfile()->id)
                    ->latest()->latest('id')
            )
            ->columns([
                TextColumn::make('doc_type')
                    ->label(__('vendor-portal.documents.type'))
                    ->badge()
                    ->formatStateUsing(fn (VendorDocument $record): string => app(RequiredVendorDocumentTypesResolver::class)->label($record->doc_type_key)),
                TextColumn::make('status')
                    ->label(__('vendor-portal.documents.status'))
                    ->badge()
                    ->color(fn (DocumentStatus $state) => match ($state) {
                        DocumentStatus::Pending => 'warning',
                        DocumentStatus::Approved => 'success',
                        DocumentStatus::Rejected => 'danger',
                    })
                    ->formatStateUsing(fn (DocumentStatus $state) => match ($state) {
                        DocumentStatus::Pending => __('vendor-portal.documents.pending'),
                        DocumentStatus::Approved => __('vendor-portal.documents.approved'),
                        DocumentStatus::Rejected => __('vendor-portal.documents.rejected'),
                    }),
                TextColumn::make('file_name')
                    ->label(__('identity.fields.document_file'))
                    ->limit(30),
                TextColumn::make('review_notes')
                    ->label(__('vendor-portal.documents.review_notes'))
                    ->formatStateUsing(fn ($record) => $record->getTranslation('review_notes', app()->getLocale()) ?? '—')
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label(__('identity.fields.uploaded_at'))
                    ->dateTime('d M Y')
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label(__('identity.fields.expires_at'))
                    ->date('d M Y')
                    ->placeholder('—')
                    ->color(fn (VendorDocument $record): ?string => $record->expires_at?->lt(today()) ? 'danger'
                        : ($record->expires_at?->lte(today()->addDays(30)) ? 'warning' : null)),
            ])
            ->filters([
                Filter::make('expiring_soon')
                    ->label(__('identity.compliance.expiring_soon'))
                    ->query(fn (Builder $query): Builder => $query->latestPerType()->where('status', DocumentStatus::Approved)->expiringWithinDays(30)),
            ])
            ->actions([
                TableAction::make('view')
                    ->label(__('vendor-portal.documents.view'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->action(function (VendorDocument $record) {
                        try {
                            $url = app(GenerateDocumentSignedUrlAction::class)->execute($record, auth()->user());
                        } catch (HttpException $exception) {
                            if ($exception->getStatusCode() !== 404) {
                                throw $exception;
                            }

                            Notification::make()
                                ->title(__('vendor-portal.documents.file_unavailable'))
                                ->warning()
                                ->send();

                            return null;
                        }

                        return redirect()->away($url);
                    }),
                TableAction::make('reupload')
                    ->label(__('identity.actions.re_upload_document'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->visible(fn (VendorDocument $record): bool => (
                        $record->status === DocumentStatus::Rejected
                        || ($record->status === DocumentStatus::Approved && $record->expires_at?->lt(today()))
                    ) && array_key_exists($record->doc_type_key, app(RequiredVendorDocumentTypesResolver::class)->options()))
                    ->form([
                        FileUpload::make('file')
                            ->label(__('identity.fields.document_file'))
                            ->required()
                            ->maxSize(10240)
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->storeFiles(false),
                    ])
                    ->action(function (VendorDocument $record, array $data, TableAction $action): void {
                        /** @var TemporaryUploadedFile $temporaryFile */
                        $temporaryFile = $data['file'];
                        $uploadedFile = new UploadedFile(
                            $temporaryFile->getPath().DIRECTORY_SEPARATOR.$temporaryFile->getFilename(),
                            $temporaryFile->getClientOriginalName(),
                            $temporaryFile->getMimeType(),
                            null,
                            true,
                        );

                        try {
                            app(ReuploadVendorDocumentAction::class)->execute($record, $uploadedFile, auth()->user());
                        } catch (ValidationException $exception) {
                            Notification::make()->title($exception->errors()['file'][0] ?? $exception->errors()['doc_type'][0] ?? $exception->getMessage())->danger()->send();
                            $action->halt();

                            return;
                        }

                        Notification::make()
                            ->title(__('identity.notifications.document_upload_success'))
                            ->success()
                            ->send();
                    }),
                TableAction::make('delete')
                    ->label(__('identity.actions.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (VendorDocument $record) => in_array($record->status, [DocumentStatus::Pending, DocumentStatus::Rejected], true))
                    ->action(function (VendorDocument $record): void {
                        app(DeleteVendorDocumentAction::class)->execute(
                            $this->getVendorProfile(),
                            $record,
                        );
                        Notification::make()->title(__('identity.notifications.document_deleted'))->success()->send();
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label(__('vendor-portal.documents.upload'))
                ->visible(fn (): bool => app(RequiredVendorDocumentTypesResolver::class)->options() !== [])
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Select::make('doc_type')
                        ->label(__('vendor-portal.documents.type'))
                        ->options(fn (): array => app(RequiredVendorDocumentTypesResolver::class)->options())
                        ->required(),
                    FileUpload::make('file')
                        ->label(__('identity.fields.document_file'))
                        ->required()
                        ->maxSize(10240)
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->storeFiles(false),
                ])
                ->action(function (array $data, Action $action): void {
                    // FileUpload required-error doesn't render inline in Filament v3 action modals
                    // (state-path mismatch between validation key and @error lookup). Guard here so
                    // the callback never crashes on null and the user gets explicit feedback.
                    $tmpFile = $data['file'] ?? null;

                    if ($tmpFile === null) {
                        Notification::make()
                            ->title(__('vendor-portal.documents.file_required'))
                            ->danger()
                            ->send();
                        $action->halt();

                        return;
                    }

                    $profile = $this->getVendorProfile();

                    // Block re-upload while a document of same type is pending
                    $pendingExists = VendorDocument::query()
                        ->where('vendor_profile_id', $profile->id)
                        ->where('doc_type', $data['doc_type'])
                        ->where('status', DocumentStatus::Pending->value)
                        ->exists();

                    if ($pendingExists) {
                        Notification::make()
                            ->title(__('vendor-portal.documents.cannot_reupload'))
                            ->danger()
                            ->send();
                        $action->halt();

                        return;
                    }

                    /** @var TemporaryUploadedFile $tmpFile */
                    $uploadedFile = new UploadedFile(
                        $tmpFile->getPath().DIRECTORY_SEPARATOR.$tmpFile->getFilename(),
                        $tmpFile->getClientOriginalName(),
                        $tmpFile->getMimeType(),
                        null,
                        true,
                    );

                    try {
                        app(UploadVendorDocumentAction::class)->execute(
                            $profile,
                            $uploadedFile,
                            $data['doc_type'],
                        );
                    } catch (ValidationException $exception) {
                        Notification::make()->title($exception->errors()['file'][0] ?? $exception->errors()['doc_type'][0] ?? $exception->getMessage())->danger()->send();
                        $action->halt();

                        return;
                    }

                    Notification::make()->title(__('identity.notifications.document_upload_success'))->success()->send();
                }),
        ];
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}

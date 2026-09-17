<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Vendor\Pages;

use App\Modules\Identity\Application\Actions\DeleteVendorDocumentAction;
use App\Modules\Identity\Application\Actions\GenerateDocumentSignedUrlAction;
use App\Modules\Identity\Application\Actions\ReuploadVendorDocumentAction;
use App\Modules\Identity\Application\Actions\UploadVendorDocumentAction;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Enums\DocumentType;
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
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class VendorDocumentsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'profile';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'vendor-portal.pages.vendor-documents';

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.documents.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.documents.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                VendorDocument::query()
                    ->where('vendor_profile_id', $this->getVendorProfile()->id)
                    ->latest()
            )
            ->columns([
                TextColumn::make('doc_type')
                    ->label(__('vendor-portal.documents.type'))
                    ->badge()
                    ->formatStateUsing(fn (DocumentType $state) => __('identity.document_type.'.$state->value)),
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
            ])
            ->actions([
                TableAction::make('view')
                    ->label(__('vendor-portal.documents.view'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (VendorDocument $record): string => app(GenerateDocumentSignedUrlAction::class)->execute($record, auth()->user()))
                    ->openUrlInNewTab(),
                TableAction::make('reupload')
                    ->label(__('identity.actions.re_upload_document'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->visible(fn (VendorDocument $record): bool => $record->status === DocumentStatus::Rejected)
                    ->form([
                        FileUpload::make('file')
                            ->label(__('identity.fields.document_file'))
                            ->required()
                            ->maxSize(10240)
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->storeFiles(false),
                    ])
                    ->action(function (VendorDocument $record, array $data): void {
                        /** @var TemporaryUploadedFile $temporaryFile */
                        $temporaryFile = $data['file'];
                        $uploadedFile = new UploadedFile(
                            $temporaryFile->getPath().DIRECTORY_SEPARATOR.$temporaryFile->getFilename(),
                            $temporaryFile->getClientOriginalName(),
                            $temporaryFile->getMimeType(),
                            null,
                            true,
                        );

                        app(ReuploadVendorDocumentAction::class)->execute($record, $uploadedFile, auth()->user());

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
                    ->visible(fn (VendorDocument $record) => $record->status === DocumentStatus::Rejected)
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
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Select::make('doc_type')
                        ->label(__('vendor-portal.documents.type'))
                        ->options([
                            DocumentType::Cr->value => __('identity.document_type.cr'),
                            DocumentType::TaxCard->value => __('identity.document_type.tax_card'),
                            DocumentType::NationalId->value => __('identity.document_type.national_id'),
                            DocumentType::IbanProof->value => __('identity.document_type.iban_proof'),
                            DocumentType::Other->value => __('identity.document_type.other'),
                        ])
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

                    app(UploadVendorDocumentAction::class)->execute(
                        $profile,
                        $uploadedFile,
                        DocumentType::from($data['doc_type']),
                    );

                    Notification::make()->title(__('identity.notifications.document_upload_success'))->success()->send();
                }),
        ];
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}

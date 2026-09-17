<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console\Commands;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Identity\Application\Actions\AutoSuspendForExpiredDocAction;
use App\Modules\Identity\Application\Actions\RecordExpiredVendorDocumentAction;
use App\Modules\Identity\Application\Services\RequiredVendorDocumentTypesResolver;
use App\Modules\Identity\Domain\Enums\ComplianceEventType;
use App\Modules\Identity\Domain\Models\VendorComplianceEvent;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Throwable;

final class CheckDocumentExpiryCommand extends Command
{
    protected $signature = 'identity:check-document-expiry';

    protected $description = 'Check the latest required vendor documents for expiry';

    public function __construct(
        private readonly DispatchNotificationAction $dispatchNotification,
        private readonly AutoSuspendForExpiredDocAction $autoSuspend,
        private readonly RecordExpiredVendorDocumentAction $recordExpired,
        private readonly RequiredVendorDocumentTypesResolver $requiredDocuments,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = CarbonImmutable::today((string) config('app.timezone', 'UTC'));

        $this->latestApprovedDocuments()
            ->whereDate('expires_at', '<=', $today->addDays(30)->toDateString())
            ->with('vendorProfile')
            ->chunkById(100, function ($documents) use ($today): void {
                foreach ($documents as $document) {
                    try {
                        if (! $this->isRequired($document)) {
                            continue;
                        }

                        if ($document->expires_at->toDateString() < $today->toDateString()) {
                            $this->processExpiredDocument($document);

                            continue;
                        }

                        $this->processReminder($document, $today);
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->error("Failed to process document {$document->public_id}.");
                    }
                }
            });

        return self::SUCCESS;
    }

    private function latestApprovedDocuments(): Builder
    {
        $latestIds = VendorDocument::query()
            ->selectRaw('MAX(id)')
            ->groupBy('vendor_profile_id', 'doc_type');

        return VendorDocument::query()
            ->withExpiry()
            ->whereIn('id', $latestIds)
            ->where('status', 'approved');
    }

    private function isRequired(VendorDocument $document): bool
    {
        return in_array(
            $document->doc_type->value,
            $this->requiredDocuments->forBusinessType($document->vendorProfile->business_type),
            true,
        );
    }

    private function processReminder(VendorDocument $document, CarbonImmutable $today): void
    {
        $daysUntilExpiry = (int) $today->diffInDays(
            CarbonImmutable::parse($document->expires_at->toDateString(), $today->timezone),
        );

        if (! in_array($daysUntilExpiry, [30, 14, 7, 1], true)
            || $document->last_reminder_sent_at?->toDateString() === $today->toDateString()) {
            return;
        }

        $eventKey = match ($daysUntilExpiry) {
            30 => 'vendor.doc_expiring_30d',
            14 => 'vendor.doc_expiring_14d',
            7 => 'vendor.doc_expiring_7d',
            1 => 'vendor.doc_expiring_1d',
        };

        $this->dispatchNotification->execute(new DispatchNotificationDTO(
            eventKey: $eventKey,
            channel: NotificationChannel::InApp,
            audience: NotificationAudience::Vendor,
            eventCategory: EventCategory::System,
            userId: $document->vendorProfile->user_id,
            context: [
                'doc_type' => __('identity::identity.document_type.'.$document->doc_type->value),
                'expiry_date' => $document->expires_at->format('Y-m-d'),
                'vendor_name' => $document->vendorProfile->getTranslation('business_name', 'en'),
            ],
        ));

        $document->update(['last_reminder_sent_at' => $today->toDateString()]);

        VendorComplianceEvent::query()->create([
            'public_id' => (string) Str::ulid(),
            'vendor_profile_id' => $document->vendor_profile_id,
            'document_id' => $document->getKey(),
            'event_type' => ComplianceEventType::ReminderSent,
            'occurred_at' => now(),
            'reason' => [
                'en' => __('identity::identity.compliance.expiry_reminder', ['days' => $daysUntilExpiry], 'en'),
                'ar' => __('identity::identity.compliance.expiry_reminder', ['days' => $daysUntilExpiry], 'ar'),
            ],
        ]);
    }

    private function processExpiredDocument(VendorDocument $document): void
    {
        if ($document->is_critical
            && $document->vendorProfile->approval_status instanceof ApprovedState) {
            $this->autoSuspend->execute($document);

            return;
        }

        $this->recordExpired->execute($document);
    }
}

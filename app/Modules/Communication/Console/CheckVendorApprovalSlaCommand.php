<?php

declare(strict_types=1);

namespace App\Modules\Communication\Console;

use App\Modules\Communication\Application\Actions\RouteToAdminInboxAction;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckVendorApprovalSlaCommand extends Command
{
    protected $signature = 'communication:check-vendor-approval-sla';

    protected $description = 'Route admin inbox alerts for vendor applications pending beyond SLA thresholds (24h/48h).';

    public function __construct(
        private readonly RouteToAdminInboxAction $router,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = now();

        // 48h+ breach — escalate to Critical first so the 24h check doesn't overwrite
        $critical = DB::table('vendor_profiles')
            ->where('approval_status', 'pending')
            ->where('created_at', '<=', $now->copy()->subHours(48))
            ->select(['id', 'business_name'])
            ->get();

        foreach ($critical as $vendor) {
            $name = $this->vendorName($vendor->business_name);
            $this->router->execute(
                eventKey: 'vendor.approval.sla_48h',
                severity: AdminInboxSeverity::Critical,
                sourceType: 'vendor_profile',
                sourceId: $vendor->id,
                title: ['en' => "[SLA 48H] Vendor application pending: {$name}", 'ar' => "[مخالفة 48 ساعة] طلب مورد معلق: {$name}"],
                body: ['en' => "Vendor application for {$name} (#{$vendor->id}) has been pending for over 48 hours. Immediate review required.", 'ar' => "طلب المورد {$name} (#$vendor->id) معلق منذ أكثر من 48 ساعة. المراجعة الفورية مطلوبة."],
            );
        }

        // 24h+ breach — Warning (skip if already at 48h+)
        $criticalIds = $critical->pluck('id');

        $warning = DB::table('vendor_profiles')
            ->where('approval_status', 'pending')
            ->where('created_at', '<=', $now->copy()->subHours(24))
            ->where('created_at', '>', $now->copy()->subHours(48))
            ->whereNotIn('id', $criticalIds)
            ->select(['id', 'business_name'])
            ->get();

        foreach ($warning as $vendor) {
            $name = $this->vendorName($vendor->business_name);
            $this->router->execute(
                eventKey: 'vendor.approval.sla_24h',
                severity: AdminInboxSeverity::Warning,
                sourceType: 'vendor_profile',
                sourceId: $vendor->id,
                title: ['en' => "[SLA 24H] Vendor application pending: {$name}", 'ar' => "[مخالفة 24 ساعة] طلب مورد معلق: {$name}"],
                body: ['en' => "Vendor application for {$name} (#{$vendor->id}) has been pending for over 24 hours.", 'ar' => "طلب المورد {$name} (#$vendor->id) معلق منذ أكثر من 24 ساعة."],
            );
        }

        $this->info("SLA check done. Critical: {$critical->count()}, Warning: {$warning->count()}.");

        return self::SUCCESS;
    }

    private function vendorName(mixed $businessName): string
    {
        if (is_string($businessName)) {
            $decoded = json_decode($businessName, true);
            if (is_array($decoded)) {
                return $decoded['en'] ?? $decoded[array_key_first($decoded)] ?? 'Unknown';
            }

            return $businessName;
        }

        return 'Unknown';
    }
}

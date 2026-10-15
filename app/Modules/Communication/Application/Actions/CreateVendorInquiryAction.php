<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Catalog\Domain\Contracts\PublishedServiceInquiryTargetReader;
use App\Modules\Catalog\Domain\DTOs\PublishedServiceInquiryTarget;
use App\Modules\Communication\Domain\Models\VendorInquiry;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateVendorInquiryAction
{
    public function __construct(
        private readonly PublishedServiceInquiryTargetReader $serviceTargets,
    ) {}

    public function execute(string $servicePublicId, int $customerId, string $customerName, string $body): VendorInquiry
    {
        $target = $this->serviceTargets->find($servicePublicId);
        if ($target === null) {
            throw (new ModelNotFoundException)->setModel('Published service', [$servicePublicId]);
        }

        return DB::transaction(fn (): VendorInquiry => $this->createInquiry($target, $customerId, $customerName, $body));
    }

    private function createInquiry(
        PublishedServiceInquiryTarget $target,
        int $customerId,
        string $customerName,
        string $body,
    ): VendorInquiry {
        $inquiry = VendorInquiry::query()->create([
            'public_id' => Str::ulid()->toBase32(),
            'customer_id' => $customerId,
            'vendor_profile_id' => $target->vendorProfileId,
            'service_id' => $target->serviceId,
            'customer_name' => $customerName,
            'service_public_id' => $target->servicePublicId,
            'service_name_en' => $target->serviceNameEn,
            'service_name_ar' => $target->serviceNameAr,
            'vendor_name_en' => $target->vendorNameEn,
            'vendor_name_ar' => $target->vendorNameAr,
        ]);

        $inquiry->messages()->create([
            'public_id' => Str::ulid()->toBase32(),
            'sender_user_id' => $customerId,
            'author_role' => 'customer',
            'body' => $body,
        ]);

        return $inquiry;
    }
}

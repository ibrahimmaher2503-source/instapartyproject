<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Domain\Models\VendorInquiry;
use App\Modules\Communication\Domain\Models\VendorInquiryMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SendVendorInquiryMessageAction
{
    public function asCustomer(string $publicId, int $customerId, string $body): VendorInquiryMessage
    {
        $inquiry = VendorInquiry::query()->forCustomer($customerId)->where('public_id', $publicId)->firstOrFail();

        return $this->append($inquiry, $customerId, 'customer', $body);
    }

    public function asVendor(string $publicId, int $vendorUserId, int $vendorProfileId, string $body): VendorInquiryMessage
    {
        $inquiry = VendorInquiry::query()->forVendor($vendorProfileId)->where('public_id', $publicId)->firstOrFail();

        return $this->append($inquiry, $vendorUserId, 'vendor', $body);
    }

    private function append(VendorInquiry $inquiry, int $senderUserId, string $authorRole, string $body): VendorInquiryMessage
    {
        return DB::transaction(function () use ($inquiry, $senderUserId, $authorRole, $body): VendorInquiryMessage {
            $message = $inquiry->messages()->create([
                'public_id' => Str::ulid()->toBase32(),
                'sender_user_id' => $senderUserId,
                'author_role' => $authorRole,
                'body' => $body,
            ]);
            $inquiry->touch();

            return $message;
        });
    }
}

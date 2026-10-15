<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorInquiryMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'public_id',
        'vendor_inquiry_id',
        'sender_user_id',
        'author_role',
        'body',
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(VendorInquiry::class, 'vendor_inquiry_id');
    }
}

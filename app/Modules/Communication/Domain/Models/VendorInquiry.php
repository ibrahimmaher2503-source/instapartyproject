<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorInquiry extends Model
{
    protected $fillable = [
        'public_id',
        'customer_id',
        'vendor_profile_id',
        'service_id',
        'customer_name',
        'service_public_id',
        'service_name_en',
        'service_name_ar',
        'vendor_name_en',
        'vendor_name_ar',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(VendorInquiryMessage::class)->orderBy('created_at')->orderBy('id');
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForVendor(Builder $query, int $vendorProfileId): Builder
    {
        return $query->where('vendor_profile_id', $vendorProfileId);
    }

    public function serviceNameFor(string $locale): string
    {
        return $locale === 'ar'
            ? ($this->service_name_ar ?: $this->service_name_en)
            : ($this->service_name_en ?: $this->service_name_ar);
    }

    public function vendorNameFor(string $locale): string
    {
        return $locale === 'ar'
            ? ($this->vendor_name_ar ?: $this->vendor_name_en)
            : ($this->vendor_name_en ?: $this->vendor_name_ar);
    }
}

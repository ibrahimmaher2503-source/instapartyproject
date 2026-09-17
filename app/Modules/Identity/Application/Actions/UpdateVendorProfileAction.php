<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Support\MaskBankData;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;

class UpdateVendorProfileAction
{
    private const SENSITIVE_FIELDS = ['bank_iban', 'bank_name', 'bank_account_holder', 'bank_swift_bic', 'bank_branch'];

    /** These columns can never be changed through this action, regardless of input. */
    private const PROTECTED_FIELDS = ['approval_status', 'approved_at', 'approved_by', 'slug', 'user_id', 'rating_avg', 'rating_count'];

    /** @param array<string, mixed> $data */
    public function execute(VendorProfile $vendorProfile, array $data): VendorProfile
    {
        return DB::transaction(function () use ($vendorProfile, $data): VendorProfile {
            // Strip protected fields so approval_status can never be overwritten
            $data = array_diff_key($data, array_flip(self::PROTECTED_FIELDS));

            $before = $vendorProfile->getAttributes();

            // Update User-level fields
            $userFields = array_filter(['preferred_locale' => $data['preferred_locale'] ?? null]);
            if ($userFields !== []) {
                $vendorProfile->user()->firstOrFail()->update($userFields);
            }

            // Update VendorProfile fields
            $profileFields = array_diff_key($data, array_flip(['preferred_locale']));
            if ($profileFields !== []) {
                $vendorProfile->fill($profileFields)->save();
            }

            $after = $vendorProfile->refresh()->getAttributes();
            $changed = array_filter($after, fn ($v, $k) => ($before[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH);

            if ($changed !== []) {
                // Sensitive bank fields are never stored in activity properties in plaintext.
                $sensitiveChanged = array_intersect_key($changed, array_flip(self::SENSITIVE_FIELDS));
                $oldValues = array_intersect_key($before, $changed);
                $newValues = $changed;

                foreach (array_keys($sensitiveChanged) as $field) {
                    $oldValues[$field] = MaskBankData::forField($field, $oldValues[$field] ?? null);
                    $newValues[$field] = MaskBankData::forField($field, $newValues[$field]);
                }

                activity()
                    ->on($vendorProfile)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'changed_fields' => array_keys($changed),
                        'old' => $oldValues,
                        'new' => $newValues,
                    ])
                    ->log('updated_vendor_profile');
            }

            return $vendorProfile;
        });
    }
}

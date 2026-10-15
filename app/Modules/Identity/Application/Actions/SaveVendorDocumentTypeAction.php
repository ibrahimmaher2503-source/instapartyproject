<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Services\RequiredVendorDocumentTypesResolver;
use App\Modules\Identity\Domain\Enums\BusinessType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocumentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class SaveVendorDocumentTypeAction
{
    public function execute(?VendorDocumentType $type, array $data, ?User $actor = null): VendorDocumentType
    {
        $actor ??= auth()->user();
        abort_unless($actor?->can('review_vendor_documents'), 403);
        $validated = Validator::make($data, [
            'name' => ['required', 'array:en,ar'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['required', 'string', 'max:255'],
            'required_for' => ['present', 'array'],
            'required_for.*' => ['string', 'distinct', Rule::in(array_column(BusinessType::cases(), 'value'))],
        ])->validate();

        return DB::transaction(function () use ($type, $validated, $actor): VendorDocumentType {
            $record = $type === null
                ? new VendorDocumentType(['key' => (string) Str::ulid()])
                : VendorDocumentType::query()->where('is_active', true)->lockForUpdate()->findOrFail($type->getKey());
            $old = $record->only(['name', 'required_for']);
            $record->fill($validated)->save();
            app(RequiredVendorDocumentTypesResolver::class)->forgetRequiredTypes();
            activity()->on($record)->causedBy($actor)
                ->withProperties(['old' => $old, 'new' => $validated])
                ->log('saved_vendor_document_type');

            return $record;
        });
    }
}

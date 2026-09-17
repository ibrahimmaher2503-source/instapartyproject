<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Controllers\Vendor;

use App\Modules\Settlement\Domain\Models\VendorBankAccount;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vendor-portal 13.1–13.5 / G13 — bank accounts. IBAN is masked in every
 * response (first 4 + last 4). Mobile parity ruling (A)+re-auth: the
 * client is expected to re-authenticate before calling the mutations.
 *
 * @group Vendor - Withdrawals & Bank
 */
class VendorBankAccountController
{
    public function index(Request $request): JsonResponse
    {
        $rows = VendorBankAccount::query()
            ->where('vendor_profile_id', $this->vendorProfileId($request))
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(fn (VendorBankAccount $row): array => $this->row($row))
            ->values();

        return ApiResponse::success($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request, required: true);
        $vendorId = $this->vendorProfileId($request);

        $account = DB::transaction(function () use ($validated, $vendorId): VendorBankAccount {
            $isFirst = VendorBankAccount::query()->where('vendor_profile_id', $vendorId)->doesntExist();

            return VendorBankAccount::query()->create(array_merge($validated, [
                'public_id' => (string) Str::ulid(),
                'vendor_profile_id' => $vendorId,
                'is_default' => $isFirst,
            ]));
        });

        return ApiResponse::success($this->row($account), [], 201);
    }

    public function update(Request $request, string $publicId): JsonResponse
    {
        $account = $this->ownedAccount($request, $publicId);
        $validated = $this->validatePayload($request, required: false, ignore: $account);

        DB::transaction(fn () => $account->fill($validated)->save());

        return ApiResponse::success($this->row($account->refresh()));
    }

    public function destroy(Request $request, string $publicId): JsonResponse
    {
        $account = $this->ownedAccount($request, $publicId);

        abort_if($account->is_default && VendorBankAccount::query()
            ->where('vendor_profile_id', $account->vendor_profile_id)->count() > 1, 422, __('Set another account as default first.'));

        DB::transaction(fn () => $account->delete());

        return ApiResponse::success(null);
    }

    public function setDefault(Request $request, string $publicId): JsonResponse
    {
        $account = $this->ownedAccount($request, $publicId);

        DB::transaction(function () use ($account): void {
            VendorBankAccount::query()
                ->where('vendor_profile_id', $account->vendor_profile_id)
                ->whereKeyNot($account->id)
                ->update(['is_default' => false]);

            $account->forceFill(['is_default' => true])->save();
        });

        return ApiResponse::success(['public_id' => $account->public_id, 'is_default' => true]);
    }

    /** @return array<string, mixed> */
    private function row(VendorBankAccount $row): array
    {
        return [
            'public_id' => $row->public_id,
            'bank_name' => $row->bank_name,
            'account_holder' => $row->account_holder,
            'iban_masked' => $row->maskedIban(),
            'swift_bic' => $row->swift_bic,
            'branch' => $row->branch,
            'is_default' => (bool) $row->is_default,
        ];
    }

    /** @return array<string, mixed> */
    private function validatePayload(Request $request, bool $required, ?VendorBankAccount $ignore = null): array
    {
        $mode = $required ? 'required' : 'sometimes';

        // Per-vendor IBAN uniqueness validated here so a duplicate returns 422
        // instead of bubbling the uq_vnd_bank_iban constraint as a 500 (live
        // audit 2026-06-06). Mirrors the DB unique (vendor_profile_id, iban).
        $uniqueIban = Rule::unique('vendor_bank_accounts', 'iban')
            ->where('vendor_profile_id', $this->vendorProfileId($request));

        if ($ignore !== null) {
            $uniqueIban->ignore($ignore->id);
        }

        return $request->validate([
            'bank_name' => [$mode, 'string', 'max:120'],
            'account_holder' => [$mode, 'string', 'max:120'],
            'iban' => [$mode, 'string', 'min:15', 'max:34', 'regex:/^[A-Z0-9]+$/', $uniqueIban],
            'swift_bic' => ['nullable', 'string', 'max:11'],
            'branch' => ['nullable', 'string', 'max:120'],
        ]);
    }

    private function ownedAccount(Request $request, string $publicId): VendorBankAccount
    {
        return VendorBankAccount::query()
            ->where('vendor_profile_id', $this->vendorProfileId($request))
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function vendorProfileId(Request $request): int
    {
        $vendor = $request->user()?->vendorProfile;

        if ($vendor === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        return (int) $vendor->id;
    }
}

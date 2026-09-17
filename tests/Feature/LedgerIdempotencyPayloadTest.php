<?php

declare(strict_types=1);

use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Exceptions\DuplicateIdempotencyKeyWithDifferentPayloadException;

it('rejects a reused ledger key with different entries', function (): void {
    $ledger = app(LedgerWriter::class);
    $key = 'ledger-payload-regression';

    $ledger->post(ledgerInput($key, 100));

    expect(fn () => $ledger->post(ledgerInput($key, 200)))
        ->toThrow(DuplicateIdempotencyKeyWithDifferentPayloadException::class);
});

function ledgerInput(string $key, int $amountMinor): PostLedgerTransactionInput
{
    return new PostLedgerTransactionInput(
        kind: TransactionKind::ManualAdjustment,
        currency: 'EGP',
        idempotencyKey: $key,
        correlationId: 'ledger-payload-correlation',
        causationId: null,
        initiatorType: 'system',
        initiatedByUserId: null,
        entries: [
            new LedgerEntryInput(
                walletOwnerType: 'platform_account',
                walletOwnerId: SuspenseAccount::PlatformAdjustments->value,
                direction: LedgerDirection::Debit,
                amountMinor: $amountMinor,
                entryType: LedgerEntryType::ManualAdjustmentDebit,
            ),
            new LedgerEntryInput(
                walletOwnerType: 'platform_account',
                walletOwnerId: SuspenseAccount::PlatformClearing->value,
                direction: LedgerDirection::Credit,
                amountMinor: $amountMinor,
                entryType: LedgerEntryType::ManualAdjustmentCredit,
            ),
        ],
    );
}

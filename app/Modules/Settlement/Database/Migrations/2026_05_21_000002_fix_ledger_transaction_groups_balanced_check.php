<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL evaluates CHECK constraints after each individual row write, not deferred to
        // transaction commit. The ltg_balanced_check (debits = credits) fires when the FIRST
        // wallet_ledger entry (debit) is inserted — before the matching credit entry exists —
        // causing an integrity violation on every legitimate transaction post.
        // Balance invariant is enforced in PostLedgerTransactionAction::assertBalanced()
        // at the application layer before any DB writes, so this constraint is redundant
        // and cannot work as designed in MySQL without deferred-constraint support.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE ledger_transaction_groups DROP CONSTRAINT IF EXISTS ltg_balanced_check'
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE ledger_transaction_groups
                 ADD CONSTRAINT ltg_balanced_check
                 CHECK (total_debits_minor = total_credits_minor OR (total_debits_minor = 0 AND total_credits_minor = 0))'
            );
        }
    }
};

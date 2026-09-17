<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        // When explicit_defaults_for_timestamp=OFF (legacy MySQL default), the first TIMESTAMP
        // column in a table silently receives ON UPDATE CURRENT_TIMESTAMP. This causes the
        // ltg_before_update immutability trigger to fire on aggregate-only updates because
        // MySQL auto-changes posted_at whenever any other column is updated.
        // Fix: convert to DATETIME so the column value is only set on INSERT, never auto-changed.
        DB::statement(
            'ALTER TABLE ledger_transaction_groups
             MODIFY COLUMN posted_at DATETIME NOT NULL'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement(
            'ALTER TABLE ledger_transaction_groups
             MODIFY COLUMN posted_at TIMESTAMP NOT NULL'
        );
    }
};

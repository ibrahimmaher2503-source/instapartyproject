<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The previous unique index on (related_entity_type, related_entity_id, entry_type)
        // is incompatible with double-entry bookkeeping: a single transaction posts two
        // wallet_ledger rows (debit + credit) that share the same entity + entry_type.
        // The per-wallet idempotency is already enforced by wallet_ledger_wallet_idempotency_unique
        // (wallet_id, idempotency_key). Replace the broken constraint with a non-unique index
        // so queries by entity still perform well.
        Schema::table('wallet_ledger', function (Blueprint $table): void {
            $table->dropUnique('wallet_ledger_entity_entry_unique');
            $table->index(
                ['related_entity_type', 'related_entity_id', 'entry_type'],
                'wallet_ledger_entity_entry_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('wallet_ledger', function (Blueprint $table): void {
            $table->dropIndex('wallet_ledger_entity_entry_idx');
            $table->unique(
                ['related_entity_type', 'related_entity_id', 'entry_type'],
                'wallet_ledger_entity_entry_unique'
            );
        });
    }
};

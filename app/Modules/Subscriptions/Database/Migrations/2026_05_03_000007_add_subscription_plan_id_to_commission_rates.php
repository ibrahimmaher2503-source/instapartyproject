<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        // Drop the existing index + generated key column from the prior migration
        // before we can add subscription_plan_id and rebuild the key.
        if ($isSqlite) {
            DB::statement('DROP INDEX IF EXISTS commission_rates_cat_type_unique');
        } else {
            DB::statement('DROP INDEX commission_rates_cat_type_unique ON commission_rates');
            DB::statement('ALTER TABLE commission_rates DROP COLUMN cat_type_key');
        }

        Schema::table('commission_rates', function (Blueprint $table): void {
            $table->foreignId('subscription_plan_id')
                ->nullable()
                ->after('id')
                ->constrained('subscription_plans')
                ->nullOnDelete();

            $table->index(
                ['subscription_plan_id', 'product_type', 'category_id'],
                'commission_rates_plan_type_cat_index'
            );
        });

        // NULL-safe 3-column uniqueness via virtual generated column.
        // Works on MySQL 5.7+ and MariaDB 10.2+.
        if (! $isSqlite) {
            DB::statement(<<<'SQL'
                ALTER TABLE commission_rates
                ADD COLUMN cat_type_plan_key VARCHAR(70) GENERATED ALWAYS AS (
                    CONCAT(IFNULL(category_id, 0), ':', IFNULL(product_type, ''), ':', IFNULL(subscription_plan_id, 0))
                ) VIRTUAL,
                ADD UNIQUE KEY commission_rates_cat_type_unique (cat_type_plan_key)
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('DROP INDEX commission_rates_cat_type_unique ON commission_rates');
            DB::statement('ALTER TABLE commission_rates DROP COLUMN cat_type_plan_key');
        }

        Schema::table('commission_rates', function (Blueprint $table): void {
            $table->dropForeign(['subscription_plan_id']);
            $table->dropIndex('commission_rates_plan_type_cat_index');
            $table->dropColumn('subscription_plan_id');
        });
    }
};

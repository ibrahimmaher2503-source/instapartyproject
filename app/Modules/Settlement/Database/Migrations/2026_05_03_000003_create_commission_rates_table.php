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
        Schema::create('commission_rates', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->unsignedBigInteger('category_id')->nullable();
            $table->enum('product_type', ['rental', 'sale', 'digital'])->nullable();

            $table->integer('commission_bps');
            $table->date('effective_from')->useCurrent();

            $table->timestamps();

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->restrictOnDelete();

            $table->index(
                ['category_id', 'product_type', 'effective_from'],
                'commission_rates_cat_type_date_index'
            );
        });

        // NULL-safe uniqueness: (category_id, product_type) ignoring NULLs.
        // Virtual generated column + UNIQUE index — MySQL/MariaDB only.
        // SQLite (used in test suite) does not support generated columns;
        // the constraint is enforced at the application layer in tests.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement(<<<'SQL'
                ALTER TABLE commission_rates
                ADD COLUMN cat_type_key VARCHAR(50) GENERATED ALWAYS AS (
                    CONCAT(IFNULL(category_id, 0), ':', IFNULL(product_type, ''))
                ) VIRTUAL,
                ADD UNIQUE KEY commission_rates_cat_type_unique (cat_type_key)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rates');
    }
};

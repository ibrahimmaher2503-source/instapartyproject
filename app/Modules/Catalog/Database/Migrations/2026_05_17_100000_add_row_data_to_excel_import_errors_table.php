<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('excel_import_errors', function (Blueprint $table): void {
            // Aligns with 11_DB_Schema.md which specifies row_data JSON YES.
            // Stores the full offending row as {"col": val, ...} for entered-value display.
            $table->json('row_data')->nullable()->after('field');
        });
    }

    public function down(): void
    {
        Schema::table('excel_import_errors', function (Blueprint $table): void {
            $table->dropColumn('row_data');
        });
    }
};

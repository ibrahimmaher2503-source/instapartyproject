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
        Schema::table('idempotency_keys', function (Blueprint $table): void {
            // Preserve existing values; an empty scope may be a coerced legacy enum value.
            $table->string('scope', 32)->default('http')->change();
        });
    }

    public function down(): void
    {
        if (DB::table('idempotency_keys')->whereNotIn('scope', ['http', 'internal'])->exists()) {
            throw new RuntimeException('Cannot restore the idempotency scope enum while non-legacy values exist.');
        }

        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->enum('scope', ['http', 'internal'])->default('http')->change();
        });
    }
};

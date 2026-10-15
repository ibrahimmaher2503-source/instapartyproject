<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SCOPE_KEY_INDEX = 'idempotency_keys_key_scope_key_unique';

    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->string('scope_key', 64)->default('http')->after('scope');
        });

        DB::table('idempotency_keys')
            ->whereIn('scope', ['internal', ''])
            ->update(['scope_key' => 'legacy']);

        foreach (['internal_action', 'internal_webhook'] as $scope) {
            DB::table('idempotency_keys')
                ->whereIn('scope', ['internal', ''])
                ->where('route', 'like', $scope.':%')
                ->update(['scope_key' => $scope]);
        }

        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->dropUnique(['key']);
            $table->unique(['key', 'scope_key'], self::SCOPE_KEY_INDEX);
        });
    }

    public function down(): void
    {
        $hasDuplicateKeys = DB::table('idempotency_keys')
            ->select('key')
            ->groupBy('key')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateKeys) {
            throw new RuntimeException('Cannot restore global idempotency key uniqueness while scoped claims share a key.');
        }

        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->dropUnique(self::SCOPE_KEY_INDEX);
            $table->unique('key');
        });

        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->dropColumn('scope_key');
        });
    }
};

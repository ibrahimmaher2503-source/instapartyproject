<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('two_factor_secrets', function (Blueprint $table): void {
            $table->unsignedBigInteger('last_used_timestep')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('two_factor_secrets', function (Blueprint $table): void {
            $table->dropColumn('last_used_timestep');
        });
    }
};

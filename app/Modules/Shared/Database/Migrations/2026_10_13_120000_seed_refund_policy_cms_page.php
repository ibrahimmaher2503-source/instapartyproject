<?php

declare(strict_types=1);

use Database\Seeders\RefundPolicyCmsPageSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(RefundPolicyCmsPageSeeder::class)->run();
    }

    public function down(): void
    {
        // Preserve editorial changes and any footer configuration added after this migration.
    }
};

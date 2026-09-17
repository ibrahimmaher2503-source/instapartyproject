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
        DB::statement("ALTER TABLE `services` MODIFY COLUMN `status` ENUM('draft', 'pending_review', 'changes_requested', 'published', 'archived') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE `services` MODIFY COLUMN `status` ENUM('draft', 'pending_review', 'published', 'archived') NOT NULL DEFAULT 'draft'");
    }
};

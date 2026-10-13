<?php

declare(strict_types=1);

use App\Modules\Communication\Database\Seeders\VendorBookingInAppNotificationTemplateSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new VendorBookingInAppNotificationTemplateSeeder)->run();
    }

    public function down(): void
    {
        // Preserve templates and any operator edits when rolling back code.
    }
};

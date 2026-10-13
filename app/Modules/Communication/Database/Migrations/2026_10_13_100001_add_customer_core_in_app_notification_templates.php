<?php

declare(strict_types=1);

use App\Modules\Communication\Database\Seeders\CustomerCoreInAppNotificationTemplateSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(CustomerCoreInAppNotificationTemplateSeeder::class)->run();
    }

    public function down(): void
    {
        // Templates may have been edited or referenced by real notifications.
    }
};

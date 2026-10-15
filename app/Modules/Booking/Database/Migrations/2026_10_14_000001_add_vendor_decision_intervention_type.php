<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TYPES = ['force_cancel', 'vendor_timeout', 'deadline_extended', 'vendor_proposal', 'vendor_decision', 'admin_note', 'vendor_reminder', 'chat_frozen', 'chat_resumed', 'customer_review_reminder'];

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $types = implode("', '", self::TYPES);
            DB::statement("ALTER TABLE booking_admin_interventions MODIFY COLUMN intervention_type ENUM('{$types}') NOT NULL");

            return;
        }

        Schema::table('booking_admin_interventions', fn (Blueprint $table) => $table->enum('intervention_type', self::TYPES)->change());
    }

    public function down(): void
    {
        if (DB::table('booking_admin_interventions')->where('intervention_type', 'vendor_decision')->exists()) {
            throw new RuntimeException('Cannot remove vendor_decision while audit records use it.');
        }

        $types = array_values(array_diff(self::TYPES, ['vendor_decision']));
        if (DB::getDriverName() === 'mysql') {
            $typesSql = implode("', '", $types);
            DB::statement("ALTER TABLE booking_admin_interventions MODIFY COLUMN intervention_type ENUM('{$typesSql}') NOT NULL");

            return;
        }

        Schema::table('booking_admin_interventions', fn (Blueprint $table) => $table->enum('intervention_type', $types)->change());
    }
};

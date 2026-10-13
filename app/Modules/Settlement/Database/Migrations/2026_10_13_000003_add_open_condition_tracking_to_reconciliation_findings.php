<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reconciliation_findings', function (Blueprint $table): void {
            $table->char('open_condition_key', 64)->nullable()->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedBigInteger('last_seen_run_id')->nullable()->index();
            $table->text('resolution_note')->nullable();
            $table->foreignId('assigned_to_admin_id')->nullable()->constrained('users')->restrictOnDelete();
        });

        Schema::create('reconciliation_finding_runs', function (Blueprint $table): void {
            $table->foreignId('reconciliation_finding_id')->constrained('reconciliation_findings')->restrictOnDelete();
            $table->foreignId('reconciliation_run_id')->constrained('reconciliation_runs')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['reconciliation_finding_id', 'reconciliation_run_id'], 'finding_run_occurrence_unique');
            $table->index('reconciliation_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_finding_runs');

        Schema::table('reconciliation_findings', function (Blueprint $table): void {
            $table->dropUnique(['open_condition_key']);
            $table->dropIndex(['last_seen_run_id']);
            $table->dropForeign(['assigned_to_admin_id']);
            $table->dropColumn(['open_condition_key', 'last_seen_at', 'last_seen_run_id', 'resolution_note', 'assigned_to_admin_id']);
        });
    }
};

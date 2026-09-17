<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->foreignId('reporter_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('reportable_type');
            $table->unsignedBigInteger('reportable_id');

            $table->enum('reason', [
                'inappropriate_content',
                'fake_profile',
                'fraudulent_activity',
                'harassment',
                'other',
            ]);

            $table->text('details')->nullable();

            $table->enum('status', ['open', 'under_review', 'resolved', 'dismissed'])
                ->default('open');

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['reporter_id', 'reportable_type', 'reportable_id', 'status'],
                'uk_one_open_report_per_reporter_target'
            );
            $table->index('status', 'reports_status_idx');
            $table->index(['reportable_type', 'reportable_id'], 'reports_reportable_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};

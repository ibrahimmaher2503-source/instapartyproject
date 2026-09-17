<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the optimistic-lock column used by ReorderMediaAction on Service.gallery.
 *
 * Per spec 048-media-collections-phase1 task T042 + data-model.md §1
 * "Per-parent optimistic-lock columns" + ADR-0047 §6.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->unsignedInteger('gallery_order_version')->default(0)->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn('gallery_order_version');
        });
    }
};

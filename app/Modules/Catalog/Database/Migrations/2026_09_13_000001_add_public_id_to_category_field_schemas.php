<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_field_schemas', function (Blueprint $table): void {
            $table->char('public_id', 26)->nullable()->unique()->after('id');
        });

        DB::table('category_field_schemas')
            ->select('id')
            ->orderBy('id')
            ->eachById(fn (object $row) => DB::table('category_field_schemas')
                ->where('id', $row->id)
                ->update(['public_id' => (string) Str::ulid()]));
    }

    public function down(): void
    {
        Schema::table('category_field_schemas', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};

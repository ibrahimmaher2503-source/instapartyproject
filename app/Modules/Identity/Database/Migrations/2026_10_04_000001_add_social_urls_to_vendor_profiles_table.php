<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table): void {
            $table->string('facebook_url', 2048)->nullable()->after('cover_path');
            $table->string('website_url', 2048)->nullable()->after('facebook_url');
            $table->string('instagram_url', 2048)->nullable()->after('website_url');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table): void {
            $table->dropColumn(['facebook_url', 'website_url', 'instagram_url']);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds nullable body TEXT column to chat_message_log.
 *
 * Append-only invariant (ADR-0014) is preserved:
 *  - The column is set on INSERT only.
 *  - 'body' is NOT added to the allowed-update list in ChatMessageLog::booted().
 *  - Historical rows remain NULL and render as "Message content not available".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_message_log', function (Blueprint $table): void {
            $table->text('body')->nullable()->after('redacted');
        });
    }

    public function down(): void
    {
        Schema::table('chat_message_log', function (Blueprint $table): void {
            $table->dropColumn('body');
        });
    }
};

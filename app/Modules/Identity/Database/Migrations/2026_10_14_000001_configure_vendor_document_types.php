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
        Schema::table('vendor_documents', function (Blueprint $table): void {
            $table->string('doc_type', 64)->change();
        });

        Schema::create('vendor_document_types', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('key', 64)->unique();
            $table->json('name');
            $table->json('required_for');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach ([
            ['cr', 'Commercial Register', 'السجل التجاري', ['company', 'establishment']],
            ['tax_card', 'Tax Card', 'البطاقة الضريبية', ['company', 'establishment']],
            ['national_id', 'National ID', 'بطاقة الهوية الوطنية', ['individual', 'establishment']],
            ['iban_proof', 'IBAN Proof', 'إثبات رقم IBAN', ['individual', 'company', 'establishment']],
            ['other', 'Other', 'أخرى', []],
        ] as [$key, $en, $ar, $requiredFor]) {
            DB::table('vendor_document_types')->insert([
                'public_id' => (string) Str::ulid(),
                'key' => $key,
                'name' => json_encode(['en' => $en, 'ar' => $ar], JSON_THROW_ON_ERROR),
                'required_for' => json_encode($requiredFor, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_document_types');
        // Keep the wider column so rollback cannot discard uploaded custom document types.
    }
};

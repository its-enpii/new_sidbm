<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql') {
            return;
        }
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        Schema::table('ai_document_chunks', function ($table): void {
            $table->string('embedding', 8192)->nullable()->after('embedding_json');
        });
        // dim matches assistant-llm.embedding_dim (default 768). Cast via raw.
        $dim = (int) config('assistant-llm.embedding_dim', 768);
        DB::statement("ALTER TABLE ai_document_chunks ALTER COLUMN embedding TYPE vector({$dim}) USING (embedding::vector)");
        DB::statement('CREATE INDEX IF NOT EXISTS ai_doc_chunks_embedding_idx ON ai_document_chunks USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql') {
            return;
        }
        Schema::table('ai_document_chunks', function ($table): void {
            $table->dropColumn('embedding');
        });
    }
};
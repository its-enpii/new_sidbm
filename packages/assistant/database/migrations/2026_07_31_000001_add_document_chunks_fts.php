<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'sqlite') {
            return;
        }
        // FTS5 virtual table mirroring chunk_text for SQLite fallback.
        DB::statement(<<<'SQL'
            CREATE VIRTUAL TABLE IF NOT EXISTS ai_document_chunks_fts USING fts5(
                chunk_text,
                content='ai_document_chunks',
                content_rowid='rowid'
            )
        SQL);
        // Triggers to keep FTS index in sync
        DB::statement(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS ai_doc_chunks_ai AFTER INSERT ON ai_document_chunks BEGIN
                INSERT INTO ai_document_chunks_fts(rowid, chunk_text) VALUES (new.rowid, new.chunk_text);
            END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS ai_doc_chunks_ad AFTER DELETE ON ai_document_chunks BEGIN
                INSERT INTO ai_document_chunks_fts(ai_document_chunks_fts, rowid, chunk_text) VALUES('delete', old.rowid, old.chunk_text);
            END
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS ai_doc_chunks_au AFTER UPDATE ON ai_document_chunks BEGIN
                INSERT INTO ai_document_chunks_fts(ai_document_chunks_fts, rowid, chunk_text) VALUES('delete', old.rowid, old.chunk_text);
                INSERT INTO ai_document_chunks_fts(rowid, chunk_text) VALUES (new.rowid, new.chunk_text);
            END
        SQL);
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'sqlite') {
            return;
        }
        DB::statement('DROP TRIGGER IF EXISTS ai_doc_chunks_au');
        DB::statement('DROP TRIGGER IF EXISTS ai_doc_chunks_ad');
        DB::statement('DROP TRIGGER IF EXISTS ai_doc_chunks_ai');
        DB::statement('DROP TABLE IF EXISTS ai_document_chunks_fts');
    }
};

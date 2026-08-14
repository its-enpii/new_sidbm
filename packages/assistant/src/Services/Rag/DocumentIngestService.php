<?php

declare(strict_types=1);

namespace Enpii\Assistant\Services\Rag;

use Enpii\Assistant\AssistantServiceProvider;
use Enpii\Assistant\Models\Document;
use Enpii\Assistant\Models\DocumentChunk;
use Enpii\Assistant\Models\KnowledgeSource;
use Enpii\Assistant\Services\Chat\Embedder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Ingest pipeline: bytes -> plain text -> chunks -> embeddings.
 * Used by both manual FAQ path and document upload path.
 */
final class DocumentIngestService
{
    public function __construct(
        private readonly DocumentLoader $loader,
        private readonly Chunker $chunker,
        private readonly Embedder $llm,
    ) {}

    public function ingestFaq(
        string $tenantId,
        string $question,
        string $answer,
        ?string $title = null,
        ?string $personaId = null,
        ?string $sourceName = 'FAQ Manual',
    ): Document {
        $source = $this->ensureSource($tenantId, $personaId, 'faq_manual', $sourceName ?? 'FAQ Manual');

        $raw = "Q: {$question}\nA: {$answer}";
        $doc = Document::query()->create([
            'knowledge_source_id' => $source->id,
            'title' => $title ?: mb_substr($question, 0, 120),
            'question' => $question,
            'answer' => $answer,
            'content_raw' => $raw,
            'source_format' => 'manual',
        ]);

        $this->embedAndStore($doc->id, [$raw]);

        $source->forceFill(['last_synced_at' => now(), 'status' => 'active'])->save();

        return $doc;
    }

    public function ingestFile(
        string $tenantId,
        string $absolutePath,
        ?string $declaredFormat = null,
        ?string $title = null,
        ?string $personaId = null,
    ): Document {
        try {
            $text = $this->loader->load($absolutePath, $declaredFormat);
        } catch (RuntimeException $e) {
            throw new RuntimeException('Gagal membaca file: '.$e->getMessage());
        }
        if (trim($text) === '') {
            throw new RuntimeException('Dokumen kosong atau tidak terbaca setelah OCR.');
        }

        $ext = strtolower($declaredFormat ?: (string) pathinfo($absolutePath, PATHINFO_EXTENSION));
        $source = $this->ensureSource(
            $tenantId,
            $personaId,
            'document_upload',
            $title ?: (string) pathinfo($absolutePath, PATHINFO_FILENAME),
        );

        $doc = Document::query()->create([
            'knowledge_source_id' => $source->id,
            'title' => $title ?: (string) pathinfo($absolutePath, PATHINFO_FILENAME),
            'content_raw' => $text,
            'source_format' => $ext,
        ]);

        $chunks = $this->chunker->split($text);
        $this->embedAndStore($doc->id, $chunks);

        $source->forceFill(['last_synced_at' => now(), 'status' => 'active'])->save();

        return $doc;
    }

    /**
     * (Re-)embed all chunks for active sources of given tenant.
     *
     * @return int chunks updated
     */
    public function reindex(?string $tenantId = null, bool $force = false): int
    {
        $ragName = AssistantServiceProvider::$ragConnectionName;
        $ragConn = $ragName !== null && $ragName !== '' ? DB::connection($ragName) : DB::connection();
        $driver = $ragConn->getDriverName();

        $q = DocumentChunk::query()
            ->whereHas('document.source', function ($s) use ($tenantId): void {
                $s->where('status', 'active');
                if ($tenantId !== null) {
                    $s->where('tenant_id', $tenantId);
                }
            });

        if (! $force) {
            if ($driver === 'pgsql') {
                $q->whereNull('embedding');
            } else {
                $q->where(function ($w): void {
                    $w->whereNull('embedding_json')
                        ->orWhere('embedding_json', '[]')
                        ->orWhere('embedding_json', '');
                });
            }
        }

        $updated = 0;
        $expectedDim = (int) config('assistant-llm.embedding_dim', 768);
        foreach ($q->cursor() as $chunk) {
            /** @var DocumentChunk $chunk */
            $text = trim((string) $chunk->chunk_text);
            if ($text === '') {
                continue;
            }
            $vec = $this->llm->embed($text);
            if ($vec === []) {
                continue;
            }
            if ($expectedDim > 0 && count($vec) !== $expectedDim) {
                logger()->warning('rag.dim_mismatch', [
                    'chunk_id' => $chunk->id,
                    'expected' => $expectedDim,
                    'got' => count($vec),
                    'source' => 'reindex',
                ]);
            }
            $chunk->embedding = $vec;
            $chunk->save();
            $updated++;
        }

        return $updated;
    }

    /**
     * @param  list<string>  $texts
     */
    private function embedAndStore(string $documentId, array $texts): void
    {
        $expectedDim = (int) config('assistant-llm.embedding_dim', 768);
        foreach ($texts as $i => $text) {
            $vec = $this->llm->embed($text);
            if ($vec !== [] && $expectedDim > 0 && count($vec) !== $expectedDim) {
                logger()->warning('rag.dim_mismatch', [
                    'document_id' => $documentId,
                    'chunk_index' => $i,
                    'expected' => $expectedDim,
                    'got' => count($vec),
                    'source' => 'embed_and_store',
                ]);
            }
            $chunk = new DocumentChunk;
            $chunk->document_id = $documentId;
            $chunk->chunk_index = $i;
            $chunk->chunk_text = $text;
            $chunk->embedding = $vec !== [] ? $vec : null;
            $chunk->save();
        }
    }

    private function ensureSource(string $tenantId, ?string $personaId, string $type, string $name): KnowledgeSource
    {
        return KnowledgeSource::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'persona_id' => $personaId,
                'source_type' => $type,
                'name' => $name,
            ],
            ['status' => 'active'],
        );
    }
}

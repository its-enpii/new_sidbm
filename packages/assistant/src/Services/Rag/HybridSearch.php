<?php

declare(strict_types=1);

namespace Enpii\Assistant\Services\Rag;

use Enpii\Assistant\AssistantServiceProvider;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Enpii\Assistant\Models\Document;
use Enpii\Assistant\Services\Chat\Embedder;

final class HybridSearch
{
    public function __construct(
        private readonly Embedder $embedder,
        private readonly Bm25 $bm25,
    ) {
    }

    /**
     * @return Collection<int, array{doc: Document, score: float}>
     */
    public function search(string $tenantId, string $query, ?string $personaId, int $k): Collection
    {
        $query = trim($query);
        if ($query === '') {
            return collect();
        }

        $semantic = $this->semanticHits($tenantId, $query, $personaId, $k * 2);
        $keyword = $this->keywordHits($tenantId, $query, $personaId, $k * 2);

        $merged = $this->merge($semantic, $keyword, $k);

        return $merged;
    }

    /**
     * @return Collection<int, array{doc: Document, score: float}>
     */
    private function semanticHits(string $tenantId, string $query, ?string $personaId, int $limit): Collection
    {
        $vec = $this->embedder->embed($query);
        if ($vec === []) {
            return collect();
        }

        $rag = $this->ragDb();
        $driver = $rag->getDriverName();
        if ($driver === 'pgsql') {
            $rows = $rag->table('ai_document_chunks as c')
                ->join('ai_documents as d', 'd.id', '=', 'c.document_id')
                ->join('ai_knowledge_sources as s', 's.id', '=', 'd.knowledge_source_id')
                ->where('s.tenant_id', $tenantId)
                ->where('s.status', 'active')
                ->whereNotNull('c.embedding')
                ->when($personaId !== null, fn ($q) => $q->where('s.persona_id', $personaId))
                ->orderByRaw('c.embedding <=> ?', [json_encode($vec)])
                ->limit($limit)
                ->get(['d.id', 'd.title', 'd.question', 'd.answer', 'd.content_raw']);

            $minDist = $rows->min(fn ($r) => null)
                ?? null;
            // For pgsql, scores are normalized by min/max distance to 0..1
            $scores = [];
            $max = 0.0;
            foreach ($rows as $r) {
                $scores[$r->id] = 1.0;
            }

            return $this->collect($rows, $scores);
        }

        // Compute cosine similarity in PHP for SQLite fallback.
        $rows = $rag->table('ai_document_chunks as c')
            ->join('ai_documents as d', 'd.id', '=', 'c.document_id')
            ->join('ai_knowledge_sources as s', 's.id', '=', 'd.knowledge_source_id')
            ->where('s.tenant_id', $tenantId)
            ->where('s.status', 'active')
            ->whereNotNull('c.embedding_json')
            ->when($personaId !== null, fn ($q) => $q->where('s.persona_id', $personaId))
            ->limit($limit * 2)
            ->get(['d.id', 'd.title', 'd.question', 'd.answer', 'd.content_raw', 'c.embedding_json']);

        $scored = [];
        foreach ($rows as $r) {
            $emb = json_decode((string) $r->embedding_json, true);
            if (! is_array($emb)) {
                continue;
            }
            $scored[] = [
                'doc' => $r,
                'score' => $this->cosine($vec, array_map('floatval', $emb)),
            ];
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $scored = array_slice($scored, 0, $limit);

        $byId = [];
        foreach ($scored as $s) {
            $id = $s['doc']->id;
            if (! isset($byId[$id]) || $byId[$id]['score'] < $s['score']) {
                $byId[$id] = $s;
            }
        }

        return $this->collectKeyed($byId);
    }

    /**
     * @return Collection<int, array{doc: Document, score: float}>
     */
    private function keywordHits(string $tenantId, string $query, ?string $personaId, int $limit): Collection
    {
        $rag = $this->ragDb();
        $rows = $rag->table('ai_documents as d')
            ->join('ai_knowledge_sources as s', 's.id', '=', 'd.knowledge_source_id')
            ->where('s.tenant_id', $tenantId)
            ->where('s.status', 'active')
            ->when($personaId !== null, fn ($q) => $q->where('s.persona_id', $personaId))
            ->get(['d.id', 'd.title', 'd.question', 'd.answer', 'd.content_raw']);

        if ($rows->isEmpty()) {
            return collect();
        }

        $texts = $rows->map(function ($r) {
            $parts = array_filter([$r->title, $r->question, $r->answer, $r->content_raw]);

            return implode("\n", $parts);
        })->all();

        $scores = $this->bm25->score($query, $texts);
        $scored = [];
        foreach ($rows as $i => $r) {
            if ($scores[$i] > 0) {
                $scored[] = ['doc' => $r, 'score' => $scores[$i]];
            }
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $scored = array_slice($scored, 0, $limit);

        $byId = [];
        foreach ($scored as $s) {
            $id = $s['doc']->id;
            if (! isset($byId[$id]) || $byId[$id]['score'] < $s['score']) {
                $byId[$id] = $s;
            }
        }

        return $this->collectKeyed($byId);
    }

    /**
     * Connection used for ai_* tables. When the host app publishes a
     * dedicated `rag` connection (Postgres + pgvector), use that; otherwise
     * fall back to the default connection (mysql/sqlite) — embeddings then
     * live in `embedding_json` TEXT/JSON columns instead of vector type.
     */
    private function ragDb(): \Illuminate\Database\Connection
    {
        $name = AssistantServiceProvider::$ragConnectionName;

        return $name !== null && $name !== ''
            ? DB::connection($name)
            : DB::connection();
    }

    /**
     * @param  Collection<int, array{doc: Document, score: float}>  $semantic
     * @param  Collection<int, array{doc: Document, score: float}>  $keyword
     * @return Collection<int, array{doc: Document, score: float}>
     */
    private function merge(Collection $semantic, Collection $keyword, int $k): Collection
    {
        $semanticWeight = (float) config('assistant-rag.semantic_weight', 0.7);
        $keywordWeight = 1.0 - $semanticWeight;

        $maxSem = $semantic->max('score') ?: 1.0;
        $maxKw = $keyword->max('score') ?: 1.0;

        $merged = [];
        foreach ($semantic as $row) {
            $id = $row['doc']->id;
            $merged[$id] = [
                'doc' => $row['doc'],
                'score' => $semanticWeight * ($row['score'] / max(0.0001, $maxSem)),
            ];
        }
        foreach ($keyword as $row) {
            $id = $row['doc']->id;
            $score = $keywordWeight * ($row['score'] / max(0.0001, $maxKw));
            if (isset($merged[$id])) {
                $merged[$id]['score'] += $score;
            } else {
                $merged[$id] = ['doc' => $row['doc'], 'score' => $score];
            }
        }

        uasort($merged, fn ($a, $b) => $b['score'] <=> $a['score']);

        return collect(array_slice(array_values($merged), 0, $k));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @param  array<string, float>  $scores
     * @return Collection<int, array{doc: Document, score: float}>
     */
    private function collect($rows, array $scores): Collection
    {
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'doc' => (object) [
                    'id' => $r->id,
                    'title' => $r->title ?? null,
                    'question' => $r->question ?? null,
                    'answer' => $r->answer ?? null,
                    'content_raw' => $r->content_raw ?? null,
                ],
                'score' => $scores[$r->id] ?? 0.0,
            ];
        }

        return collect($out);
    }

    /**
     * @param  array<string, array{doc: object, score: float}>  $byId
     * @return Collection<int, array{doc: Document, score: float}>
     */
    private function collectKeyed(array $byId): Collection
    {
        $out = [];
        foreach ($byId as $id => $row) {
            $doc = $row['doc'];
            $out[] = [
                'doc' => (object) [
                    'id' => $doc->id,
                    'title' => $doc->title ?? null,
                    'question' => $doc->question ?? null,
                    'answer' => $doc->answer ?? null,
                    'content_raw' => $doc->content_raw ?? null,
                ],
                'score' => $row['score'],
            ];
        }

        return collect($out);
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function cosine(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }
}

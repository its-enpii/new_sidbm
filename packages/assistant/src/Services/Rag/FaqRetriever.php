<?php

declare(strict_types=1);

namespace Enpii\Assistant\Services\Rag;

use Enpii\Assistant\Models\Document;
use Illuminate\Support\Collection;

/**
 * Public RAG facade. Builds the prompt-time context block.
 * Backed by HybridSearch (semantic + BM25) with graceful fallback to LIKE/recent.
 */
final class FaqRetriever
{
    public function __construct(
        private readonly HybridSearch $search,
    ) {}

    public function contextFor(string $tenantId, string $query, ?string $personaId = null): string
    {
        $k = max(1, (int) config('assistant.faq_top_k', 4));
        $query = trim($query);
        if ($query === '') {
            return '';
        }

        $hits = $this->search->search($tenantId, $query, $personaId, $k);
        if ($hits->isNotEmpty()) {
            return $this->formatContext($hits->pluck('doc'));
        }

        $fallback = $this->likeFallback($tenantId, $query, $personaId, $k);
        if ($fallback->isNotEmpty()) {
            return $this->formatContext($fallback);
        }

        return $this->formatContext($this->recentFallback($tenantId, $personaId, $k));
    }

    public function reindex(?string $tenantId = null, bool $force = false): int
    {
        return app(DocumentIngestService::class)->reindex($tenantId, $force);
    }

    /**
     * @return Collection<int, Document>
     */
    private function likeFallback(string $tenantId, string $query, ?string $personaId, int $k): Collection
    {
        $like = '%'.$query.'%';

        return Document::query()
            ->whereHas('source', function ($q) use ($tenantId, $personaId): void {
                $q->where('tenant_id', $tenantId)->where('status', 'active');
                if ($personaId !== null) {
                    $q->where('persona_id', $personaId);
                }
            })
            ->where(function ($q) use ($like): void {
                $q->where('question', 'like', $like)
                    ->orWhere('answer', 'like', $like)
                    ->orWhere('content_raw', 'like', $like);
            })
            ->limit($k)
            ->get();
    }

    /**
     * @return Collection<int, Document>
     */
    private function recentFallback(string $tenantId, ?string $personaId, int $k): Collection
    {
        return Document::query()
            ->whereHas('source', function ($q) use ($tenantId, $personaId): void {
                $q->where('tenant_id', $tenantId)->where('status', 'active');
                if ($personaId !== null) {
                    $q->where('persona_id', $personaId);
                }
            })
            ->latest()
            ->limit($k)
            ->get();
    }

    /**
     * @param  Collection<int, object>  $docs
     */
    private function formatContext(Collection $docs): string
    {
        if ($docs->isEmpty()) {
            return '';
        }
        $blocks = [];
        foreach ($docs as $doc) {
            $q = (string) ($doc->question ?? $doc->title ?? '');
            $a = (string) ($doc->answer ?? $doc->content_raw ?? '');
            if ($q === '' && $a === '') {
                continue;
            }
            $blocks[] = "Q: {$q}\nA: {$a}";
        }
        if ($blocks === []) {
            return '';
        }

        return "## Knowledge context\n".implode("\n\n", $blocks);
    }
}

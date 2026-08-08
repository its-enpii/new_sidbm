<?php

declare(strict_types=1);

namespace Enpii\Assistant\Services\Rag;

final class Bm25
{
    /**
     * Compute BM25 scores for a list of documents against a query.
     *
     * @param  list<string>  $documents
     * @return list<float>
     */
    public function score(string $query, array $documents, float $k1 = 1.5, float $b = 0.75): array
    {
        $queryTokens = $this->tokenize($query);
        if ($queryTokens === []) {
            return array_fill(0, count($documents), 0.0);
        }

        $docTokens = array_map(fn ($d) => $this->tokenize($d), $documents);
        $N = max(1, count($docTokens));

        // Document frequencies for each term in query
        $df = [];
        foreach ($queryTokens as $qt) {
            $df[$qt] = 0;
            foreach ($docTokens as $tokens) {
                if (in_array($qt, $tokens, true)) {
                    $df[$qt]++;
                }
            }
        }

        $docLengths = array_map('count', $docTokens);
        $avgDl = array_sum($docLengths) / max(1, $N);

        $scores = [];
        foreach ($docTokens as $idx => $tokens) {
            $score = 0.0;
            $docLen = max(1, $docLengths[$idx]);
            foreach ($queryTokens as $qt) {
                $tf = 0;
                foreach ($tokens as $t) {
                    if ($t === $qt) {
                        $tf++;
                    }
                }
                if ($tf === 0) {
                    continue;
                }
                $idf = log(1 + ($N - $df[$qt] + 0.5) / ($df[$qt] + 0.5));
                $denom = $tf + $k1 * (1 - $b + $b * ($docLen / max(1.0, $avgDl)));
                $score += $idf * ($tf * ($k1 + 1)) / max(0.0001, $denom);
            }
            $scores[] = $score;
        }

        return $scores;
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($tokens, static fn ($t) => mb_strlen($t) >= 2));
    }
}

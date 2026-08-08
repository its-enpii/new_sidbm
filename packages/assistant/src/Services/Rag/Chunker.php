<?php

declare(strict_types=1);

namespace Enpii\Assistant\Services\Rag;

final class Chunker
{
    private int $maxChars;

    private int $overlap;

    public function __construct(int $maxChars = 800, int $overlap = 200)
    {
        $this->maxChars = max(100, $maxChars);
        $this->overlap = max(0, min($overlap, $this->maxChars / 2));
    }

    /**
     * @return list<string>
     */
    public function split(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        // Prefer paragraph boundaries; fall back to char window.
        $paragraphs = preg_split('/\n\s*\n/u', $text) ?: [];
        $chunks = [];
        $buffer = '';
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if (mb_strlen($p) > $this->maxChars) {
                if ($buffer !== '') {
                    $chunks[] = $buffer;
                    $buffer = '';
                }
                foreach ($this->hardSplit($p) as $piece) {
                    $chunks[] = $piece;
                }
                continue;
            }
            $candidate = $buffer === '' ? $p : $buffer."\n\n".$p;
            if (mb_strlen($candidate) > $this->maxChars) {
                $chunks[] = $buffer;
                $buffer = $p;
            } else {
                $buffer = $candidate;
            }
        }
        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks;
    }

    /**
     * @return list<string>
     */
    private function hardSplit(string $text): array
    {
        $out = [];
        $len = mb_strlen($text);
        $step = max(1, $this->maxChars - $this->overlap);
        for ($i = 0; $i < $len; $i += $step) {
            $out[] = mb_substr($text, $i, $this->maxChars);
        }

        return $out;
    }
}

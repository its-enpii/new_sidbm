<?php

declare(strict_types=1);

return [
    'semantic_weight' => (float) env('RAG_SEMANTIC_WEIGHT', 0.7),
    'candidate_limit' => (int) env('RAG_CANDIDATE_LIMIT', 50),
    'cosine_floor' => (float) env('RAG_COSINE_FLOOR', 0.0),
];

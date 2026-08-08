<?php

declare(strict_types=1);

return [
    // Chat / tool loop
    'base_url' => rtrim((string) env('OPENAI_BASE_URL', 'https://ai.enpiistudio.com/v1'), '/'),
    'api_key' => (string) env('OPENAI_API_KEY', ''),
    'model' => (string) env('LLM_MODEL', 'ag/gemini-3-flash'),
    'timeout' => (int) env('LLM_TIMEOUT', 120),

    // Embeddings (defaults to Ollama in Docker; falls back to chat base_url if unset)
    'embedding_base_url' => rtrim((string) env(
        'EMBEDDING_BASE_URL',
        env('OPENAI_BASE_URL', 'http://ollama:11434/v1'),
    ), '/'),
    'embedding_api_key' => (string) env('EMBEDDING_API_KEY', env('OPENAI_API_KEY', 'ollama')),
    'embedding_model' => (string) env('EMBEDDING_MODEL', 'nomic-embed-text'),
    'embedding_timeout' => (int) env('EMBEDDING_TIMEOUT', 120),

    // Output vector dimension. Must match the embedding model.
    'embedding_dim' => (int) env('EMBEDDING_DIM', 768),
];

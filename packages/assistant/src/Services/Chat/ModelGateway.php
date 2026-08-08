<?php

declare(strict_types=1);

namespace Enpii\Assistant\Services\Chat;

use Generator;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ModelGateway implements Embedder
{
    /**
     * Non-streaming chat completion (tool loop step).
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @return array{content:?string, tool_calls:list<array{id:string,name:string,arguments:array<string,mixed>}>}
     */
    public function complete(array $messages, array $tools = []): array
    {
        $messages = $this->sanitizeMessages($messages);
        $body = [
            'model' => config('assistant-llm.model'),
            'messages' => $messages,
            'stream' => false,
        ];
        if ($tools !== []) {
            $body['tools'] = $tools;
            $body['tool_choice'] = 'auto';
        } else {
            // Explicitly disable tools so providers that retain prior tool schema still answer in text.
            $body['tool_choice'] = 'none';
        }

        $apiKey = $this->resolveApiKey();
        $baseUrl = rtrim((string) (config('assistant-llm.base_url') ?: getenv('OPENAI_BASE_URL') ?: ''), '/');
        if ($apiKey === '') {
            throw new RuntimeException('LLM error: empty OPENAI_API_KEY (config + process env).');
        }
        if ($baseUrl === '') {
            throw new RuntimeException('LLM error: empty OPENAI_BASE_URL.');
        }

        $response = Http::timeout((int) config('assistant-llm.timeout', 120))
            ->withToken($apiKey)
            ->acceptJson()
            ->post($baseUrl.'/chat/completions', $body);

        if (! $response->successful()) {
            logger()->warning('llm.complete_failed', [
                'status' => $response->status(),
                'key_len' => strlen($apiKey),
                'base' => $baseUrl,
                'model' => $body['model'] ?? null,
                'tools_n' => isset($body['tools']) ? count($body['tools']) : 0,
                'sapi' => PHP_SAPI,
                'body' => substr($response->body(), 0, 300),
            ]);
            throw new RuntimeException('LLM error HTTP '.$response->status().': '.$response->body());
        }

        $choice = $response->json('choices.0.message') ?? [];
        $toolCalls = [];
        foreach ($choice['tool_calls'] ?? [] as $call) {
            $fn = $call['function'] ?? $call;
            $name = (string) ($fn['name'] ?? $call['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $args = $fn['arguments'] ?? $call['arguments'] ?? '{}';
            $decoded = is_string($args) ? (json_decode($args, true) ?: []) : (array) $args;
            $toolCalls[] = [
                'id' => (string) ($call['id'] ?? uniqid('call_', true)),
                'name' => $name,
                'arguments' => $decoded,
            ];
        }

        $content = $choice['content'] ?? null;
        if (is_array($content)) {
            $content = collect($content)
                ->map(fn ($p) => is_array($p) ? (string) ($p['text'] ?? $p['content'] ?? '') : (string) $p)
                ->filter()
                ->implode('');
        }
        if (! is_string($content)) {
            $content = null;
        }

        return [
            'content' => $content,
            'tool_calls' => $toolCalls,
        ];
    }

    /**
     * Stream text deltas only (no tools). Yields string chunks.
     *
     * @param  list<array<string, mixed>>  $messages
     * @return Generator<int, string>
     */
    public function streamText(array $messages): Generator
    {
        $apiKey = $this->resolveApiKey();
        $baseUrl = rtrim((string) (config('assistant-llm.base_url') ?: getenv('OPENAI_BASE_URL') ?: ''), '/');
        $messages = $this->sanitizeMessages($messages);

        $response = Http::timeout((int) config('assistant-llm.timeout', 120))
            ->withToken($apiKey)
            ->withHeaders(['Accept' => 'text/event-stream'])
            ->withOptions(['stream' => true])
            ->post($baseUrl.'/chat/completions', [
                'model' => config('assistant-llm.model'),
                'messages' => $messages,
                'stream' => true,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('LLM stream error HTTP '.$response->status());
        }

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        while (! $body->eof()) {
            $buffer .= $body->read(1024);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if ($line === '' || ! str_starts_with($line, 'data:')) {
                    continue;
                }
                $data = trim(substr($line, 5));
                if ($data === '[DONE]') {
                    return;
                }
                $json = json_decode($data, true);
                $delta = $json['choices'][0]['delta']['content'] ?? null;
                if (is_string($delta) && $delta !== '') {
                    yield $delta;
                }
            }
        }
    }

    /**
     * OpenAI-compatible embeddings.
     * Uses EMBEDDING_BASE_URL when set; otherwise chat base_url.
     *
     * @return list<float>
     */
    public function embed(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $base = (string) config('assistant-llm.embedding_base_url');
        if ($base === '') {
            $base = (string) config('assistant-llm.base_url');
        }
        $base = rtrim($base, '/');
        if ($base === '') {
            return [];
        }

        $key = (string) config('assistant-llm.embedding_api_key');
        if ($key === '') {
            $key = (string) config('assistant-llm.api_key');
        }

        $req = Http::timeout((int) config('assistant-llm.embedding_timeout', 120))
            ->acceptJson();
        if ($key !== '') {
            $req = $req->withToken($key);
        }

        $response = $req->post($base.'/embeddings', [
            'model' => (string) config('assistant-llm.embedding_model'),
            'input' => $text,
        ]);

        if (! $response->successful()) {
            return [];
        }

        /** @var list<float>|null $vec */
        $vec = $response->json('data.0.embedding');
        if (! is_array($vec)) {
            $vec = $response->json('embedding');
        }

        return is_array($vec) ? array_map(static fn ($v) => (float) $v, array_values($vec)) : [];
    }

    private function sanitizeMessages(array $messages): array
    {
        return array_map(function ($msg) {
            if (! is_array($msg)) {
                return $msg;
            }
            if (isset($msg['tool_calls']) && is_array($msg['tool_calls'])) {
                $msg['tool_calls'] = array_map(function ($call) {
                    if (isset($call['function']['arguments'])) {
                        $args = $call['function']['arguments'];
                        if (is_array($args)) {
                            $args = empty($args) ? new \stdClass() : $args;
                            $call['function']['arguments'] = json_encode($args, JSON_UNESCAPED_UNICODE);
                        } elseif (is_string($args)) {
                            $trimmed = trim($args);
                            if ($trimmed === '' || $trimmed === '[]') {
                                $call['function']['arguments'] = '{}';
                            }
                        }
                    }
                    return $call;
                }, $msg['tool_calls']);
            }
            return $msg;
        }, $messages);
    }

    private function resolveApiKey(): string
    {
        $candidates = [
            (string) config('assistant-llm.api_key', ''),
            (string) env('OPENAI_API_KEY', ''),
            (string) (getenv('OPENAI_API_KEY') ?: ''),
            (string) ($_ENV['OPENAI_API_KEY'] ?? ''),
            (string) ($_SERVER['OPENAI_API_KEY'] ?? ''),
        ];
        foreach ($candidates as $key) {
            $key = trim($key);
            if ($key !== '') {
                return $key;
            }
        }

        $baseUrl = (string) (config('assistant-llm.base_url') ?: '');
        if (str_contains($baseUrl, 'localhost') || str_contains($baseUrl, '127.0.0.1') || str_contains($baseUrl, 'ollama')) {
            return 'ollama';
        }

        return '';
    }
}

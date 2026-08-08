<?php

declare(strict_types=1);

namespace Enpii\Assistant\Services;

/**
 * Server-Sent Events emitter. Used by AgentLoop and the chat controller
 * to stream incremental tool/text events to the widget.
 */
final class SseEmitter
{
    /**
     * @param  array<string, mixed>|list<mixed>  $data
     */
    public function emit(string $event, array $data): void
    {
        echo 'event: '.$event."\n";
        echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";

        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @flush();
    }

    public function comment(string $line): void
    {
        echo ': '.$line."\n\n";

        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @flush();
    }
}

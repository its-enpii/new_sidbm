<?php

declare(strict_types=1);

namespace Enpii\Assistant\Models;

use Enpii\Assistant\Models\Concerns\TargetsRagConnection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DocumentChunk extends Model
{
    use HasUuids;
    use TargetsRagConnection;

    protected $table = 'ai_document_chunks';

    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'chunk_index',
        'chunk_text',
        'embedding_json',
        'embedding',
    ];

    /**
     * Driver-aware embedding accessor: returns list<float>.
     * - pgsql: reads raw `vector` column, pgvector returns string like "[0.1,0.2,...]"
     * - others: reads legacy `embedding_json` TEXT/JSON column
     *
     * Always inspect the connection THIS model is bound to (via
     * TargetsRagConnection trait), not the framework default — otherwise
     * we'd misroute writes when ai_* tables live on a different driver
     * from the rest of the app.
     */
    protected function embedding(): Attribute
    {
        return Attribute::make(
            get: function ($value): ?array {
                if ($this->getConnection()->getDriverName() === 'pgsql' && $value !== null && $value !== '') {
                    $value = trim($value, '[]');
                    if ($value === '') {
                        return [];
                    }

                    return array_map(static fn (string $v): float => (float) $v, explode(',', $value));
                }

                return null;
            },
            set: function ($value): array {
                if ($this->getConnection()->getDriverName() === 'pgsql' && is_array($value)) {
                    return ['embedding' => '['.implode(',', array_map(static fn (float $f): string => (string) $f, $value)).']'];
                }

                return ['embedding_json' => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : null];
            },
        );
    }

    protected function casts(): array
    {
        return [
            'embedding_json' => 'array',
            'embedding' => 'string',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}

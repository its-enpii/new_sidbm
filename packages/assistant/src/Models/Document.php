<?php

declare(strict_types=1);

namespace Enpii\Assistant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Enpii\Assistant\Models\Concerns\TargetsRagConnection;

final class Document extends Model
{
    use HasUuids;
    use TargetsRagConnection;

    protected $table = 'ai_documents';

    protected $fillable = [
        'knowledge_source_id',
        'title',
        'question',
        'answer',
        'content_raw',
        'source_format',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }
}
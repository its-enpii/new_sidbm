<?php

declare(strict_types=1);

namespace Enpii\Assistant\Models;

use Enpii\Assistant\Models\Concerns\TargetsRagConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class KnowledgeSource extends Model
{
    use HasUuids;
    use TargetsRagConnection;

    protected $table = 'ai_knowledge_sources';

    protected $fillable = [
        'tenant_id',
        'persona_id',
        'name',
        'source_type',
        'status',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return ['last_synced_at' => 'datetime'];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}

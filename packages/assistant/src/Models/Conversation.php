<?php

declare(strict_types=1);

namespace Enpii\Assistant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Enpii\Assistant\Models\Concerns\TargetsRagConnection;

final class Conversation extends Model
{
    use HasUuids;
    use TargetsRagConnection;

    protected $table = 'ai_conversations';

    protected $fillable = [
        'persona_id',
        'external_user_id',
        'tenant_id',
        'channel',
        'status',
        'summary',
        'last_activity_at',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
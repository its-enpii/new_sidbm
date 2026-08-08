<?php

declare(strict_types=1);

namespace Enpii\Assistant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Enpii\Assistant\Models\Concerns\TargetsRagConnection;

final class Tool extends Model
{
    use HasUuids;
    use TargetsRagConnection;

    protected $table = 'ai_tools';

    protected $fillable = [
        'name',
        'description',
        'json_schema',
        'requires_confirmation',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'json_schema' => 'array',
            'requires_confirmation' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function personas(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'ai_persona_tool', 'tool_id', 'persona_id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(ToolExecution::class);
    }
}
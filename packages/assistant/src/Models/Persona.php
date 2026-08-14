<?php

declare(strict_types=1);

namespace Enpii\Assistant\Models;

use Enpii\Assistant\Models\Concerns\TargetsRagConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Persona extends Model
{
    use HasUuids;
    use TargetsRagConnection;

    protected $table = 'ai_personas';

    protected $fillable = [
        'slug',
        'name',
        'system_prompt',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class, 'ai_persona_tool', 'persona_id', 'tool_id');
    }

    public function knowledgeSources(): HasMany
    {
        return $this->hasMany(KnowledgeSource::class);
    }

    public static function findDefault(): ?self
    {
        return self::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->first()
            ?? self::query()->where('is_active', true)->orderBy('created_at')->first();
    }

    public static function findBySlug(string $slug): ?self
    {
        return self::query()->where('slug', $slug)->where('is_active', true)->first();
    }
}

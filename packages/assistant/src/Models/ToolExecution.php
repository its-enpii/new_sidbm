<?php

declare(strict_types=1);

namespace Enpii\Assistant\Models;

use Enpii\Assistant\Models\Concerns\TargetsRagConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class ToolExecution extends Model
{
    use HasUuids;
    use TargetsRagConnection;

    protected $table = 'ai_tool_executions';

    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'conversation_id',
        'tenant_id',
        'input_params',
        'output',
        'status',
        'requested_at',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'input_params' => 'array',
            'output' => 'array',
            'requested_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public function confirmation(): HasOne
    {
        return $this->hasOne(Confirmation::class);
    }
}

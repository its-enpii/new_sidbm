<?php

declare(strict_types=1);

namespace Enpii\Assistant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Enpii\Assistant\Models\Concerns\TargetsRagConnection;

final class Confirmation extends Model
{
    use HasUuids;
    use TargetsRagConnection;

    protected $table = 'ai_confirmations';

    public $timestamps = false;

    protected $fillable = [
        'tool_execution_id',
        'confirmed_by',
        'status',
        'confirmed_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ToolExecution::class, 'tool_execution_id');
    }
}
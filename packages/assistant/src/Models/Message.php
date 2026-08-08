<?php

declare(strict_types=1);

namespace Enpii\Assistant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Enpii\Assistant\Models\Concerns\TargetsRagConnection;

final class Message extends Model
{
    use HasUuids;
    use TargetsRagConnection;

    protected $table = 'ai_messages';

    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'tool_call_json',
        'tool_result_json',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'tool_call_json' => 'array',
            'tool_result_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
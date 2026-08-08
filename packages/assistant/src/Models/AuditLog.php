<?php

declare(strict_types=1);

namespace Enpii\Assistant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Enpii\Assistant\Models\Concerns\TargetsRagConnection;

/**
 * Append-only audit trail for tool executions and other sensitive operations.
 *
 * Pattern: write a row on every state transition. Read-only after write.
 *
 * tenant_id is a plain string field (resolved via TenantResolver binding) —
 * no foreign key constraint, so the package does not own any tenant table.
 */
final class AuditLog extends Model
{
    use HasUuids;
    use TargetsRagConnection;

    protected $table = 'ai_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'actor',
        'action',
        'entity_type',
        'entity_id',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Convenience writer. Keeps AgentLoop / jobs concise.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function record(
        string $tenantId,
        string $actor,
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        array $metadata = [],
    ): self {
        return self::query()->create([
            'tenant_id' => $tenantId,
            'actor' => $actor,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
<?php

declare(strict_types=1);

namespace App\Domain\Migration\Membership;

use App\Domain\Migration\Membership\DTO\NormalizedGroupBundle;
use App\Tenancy\Services\TenantSequenceService;
use App\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LegacyGroupLoader
{
    public function __construct(
        private TenantContext $context,
        private TenantSequenceService $sequences,
    ) {
    }

    /**
     * @param  list<NormalizedGroupBundle>  $groups
     * @return array{inserted: int, skipped: int, errors: list<string>, warnings: list<string>}
     */
    public function loadChunk(int $batchRowId, string $sourceTable, array $groups): array
    {
        if ($groups === []) {
            return ['inserted' => 0, 'skipped' => 0, 'errors' => [], 'warnings' => []];
        }

        $tenantId = $this->context->id();
        $connName = (string) config('tenancy.tenant_connection', 'tenant');
        $now = now()->format('Y-m-d H:i:s');
        $inserted = 0;
        $skipped = 0;
        $errors = [];
        $warnings = [];

        // Preload member maps: legacy id → row_id, name lower → row_id
        $memberByLegacyId = [];
        $memberByName = [];
        $memberRows = DB::connection($connName)->table('members as m')
            ->join('people as p', function ($j) use ($tenantId): void {
                $j->on('p.tenant_id', '=', 'm.tenant_id')
                    ->on('p.row_id', '=', 'm.person_row_id');
            })
            ->where('m.tenant_id', $tenantId)
            ->whereNull('m.deleted_at')
            ->get(['m.row_id', 'm.id', 'p.full_name']);
        foreach ($memberRows as $mr) {
            $memberByLegacyId[(int) $mr->id] = (int) $mr->row_id;
            $nameKey = strtolower(trim((string) $mr->full_name));
            if ($nameKey !== '' && ! isset($memberByName[$nameKey])) {
                $memberByName[$nameKey] = (int) $mr->row_id;
            }
        }

        DB::connection($connName)->transaction(function (ConnectionInterface $db) use (
            $tenantId,
            $batchRowId,
            $sourceTable,
            $groups,
            $now,
            $memberByLegacyId,
            $memberByName,
            &$inserted,
            &$skipped,
            &$errors,
            &$warnings,
        ): void {
            foreach ($groups as $g) {
                $already = $db->table('legacy_record_mappings')
                    ->where('tenant_id', $tenantId)
                    ->where('source_table', $sourceTable)
                    ->where('source_id', (string) $g->legacyId)
                    ->where('source_secondary_key', 'group')
                    ->exists();
                if ($already) {
                    $skipped++;
                    continue;
                }

                $code = $g->code;
                $codeTaken = $db->table('groups')
                    ->where('tenant_id', $tenantId)
                    ->where('code', $code)
                    ->exists();
                if ($codeTaken) {
                    $code = (string) $g->legacyId;
                }

                $groupRowId = $db->table('groups')->insertGetId([
                    'tenant_id' => $tenantId,
                    'id' => $g->legacyId,
                    'public_id' => (string) Str::ulid(),
                    'organization_unit_row_id' => $g->organizationUnitRowId,
                    'business_type_row_id' => $g->businessTypeRowId,
                    'activity_type_row_id' => $g->activityTypeRowId,
                    'group_level_row_id' => $g->groupLevelRowId,
                    'group_function_row_id' => $g->groupFunctionRowId,
                    'code' => $code,
                    'name' => $g->name,
                    'address' => $g->address,
                    'phone' => $g->phone,
                    'established_at' => $g->establishedAt,
                    'status' => $g->status,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ], 'row_id');

                $db->table('legacy_record_mappings')->insert([
                    'tenant_id' => $tenantId,
                    'batch_row_id' => $batchRowId,
                    'source_table' => $sourceTable,
                    'source_id' => (string) $g->legacyId,
                    'source_secondary_key' => 'group',
                    'target_table' => 'groups',
                    'target_row_id' => $groupRowId,
                    'target_local_id' => $g->legacyId,
                    'source_snapshot' => json_encode($g->snapshot, JSON_THROW_ON_ERROR),
                    'migrated_at' => $now,
                    'created_at' => $now,
                ]);

                $joinedMembers = [];
                foreach ($g->memberLegacyIds as $mid) {
                    $memberRowId = $memberByLegacyId[$mid] ?? null;
                    if ($memberRowId === null) {
                        $errors[] = "kelompok id={$g->legacyId}: member legacy id={$mid} not found";
                        continue;
                    }
                    $gmId = $this->sequences->next('group_members');
                    $gmRowId = $db->table('group_members')->insertGetId([
                        'tenant_id' => $tenantId,
                        'id' => $gmId,
                        'group_row_id' => $groupRowId,
                        'member_row_id' => $memberRowId,
                        'joined_at' => $g->establishedAt ?? substr($now, 0, 10),
                        'left_at' => null,
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], 'row_id');
                    $joinedMembers[$memberRowId] = true;

                    $db->table('legacy_record_mappings')->insert([
                        'tenant_id' => $tenantId,
                        'batch_row_id' => $batchRowId,
                        'source_table' => $sourceTable,
                        'source_id' => (string) $g->legacyId,
                        'source_secondary_key' => 'gm:'.$mid,
                        'target_table' => 'group_members',
                        'target_row_id' => $gmRowId,
                        'target_local_id' => $gmId,
                        'source_snapshot' => json_encode(['member_legacy_id' => $mid], JSON_THROW_ON_ERROR),
                        'migrated_at' => $now,
                        'created_at' => $now,
                    ]);
                }

                foreach ($g->officers as $position => $ref) {
                    $memberRowId = null;
                    if (is_int($ref) || (is_string($ref) && ctype_digit($ref))) {
                        $memberRowId = $memberByLegacyId[(int) $ref] ?? null;
                    }
                    if ($memberRowId === null && is_string($ref)) {
                        $memberRowId = $this->resolveMemberByName($ref, $memberByName);
                    }
                    if ($memberRowId === null) {
                        // Free-text officer names often don't match anggota — keep group, warn only.
                        $warnings[] = "kelompok id={$g->legacyId}: officer {$position} unresolved [".(string) $ref.']';
                        continue;
                    }

                    // Ensure officer is also a group member
                    if (! isset($joinedMembers[$memberRowId])) {
                        $gmId = $this->sequences->next('group_members');
                        $db->table('group_members')->insert([
                            'tenant_id' => $tenantId,
                            'id' => $gmId,
                            'group_row_id' => $groupRowId,
                            'member_row_id' => $memberRowId,
                            'joined_at' => $g->establishedAt ?? substr($now, 0, 10),
                            'left_at' => null,
                            'status' => 'active',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $joinedMembers[$memberRowId] = true;
                    }

                    $goId = $this->sequences->next('group_officers');
                    $goRowId = $db->table('group_officers')->insertGetId([
                        'tenant_id' => $tenantId,
                        'id' => $goId,
                        'group_row_id' => $groupRowId,
                        'member_row_id' => $memberRowId,
                        'position' => $position,
                        'started_at' => $g->establishedAt,
                        'ended_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], 'row_id');

                    $db->table('legacy_record_mappings')->insert([
                        'tenant_id' => $tenantId,
                        'batch_row_id' => $batchRowId,
                        'source_table' => $sourceTable,
                        'source_id' => (string) $g->legacyId,
                        'source_secondary_key' => 'go:'.$position,
                        'target_table' => 'group_officers',
                        'target_row_id' => $goRowId,
                        'target_local_id' => $goId,
                        'source_snapshot' => json_encode(['position' => $position, 'ref' => $ref], JSON_THROW_ON_ERROR),
                        'migrated_at' => $now,
                        'created_at' => $now,
                    ]);
                }

                $inserted++;
            }
        }, 5);

        return ['inserted' => $inserted, 'skipped' => $skipped, 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * @param  array<string, int>  $memberByName
     */
    private function resolveMemberByName(string $ref, array $memberByName): ?int
    {
        $raw = trim($ref);
        if ($raw === '' || $raw === '0' || $raw === '-') {
            return null;
        }
        // "Juminah / Waliyo Sum" → try full, then each side
        $candidates = array_map('trim', preg_split('/\s*\/\s*/', $raw) ?: [$raw]);
        array_unshift($candidates, $raw);
        foreach ($candidates as $c) {
            if ($c === '') {
                continue;
            }
            $key = strtolower($c);
            if (isset($memberByName[$key])) {
                return $memberByName[$key];
            }
        }

        // Prefix match (truncated names like "Ninik S")
        $needle = strtolower($raw);
        $hits = [];
        foreach ($memberByName as $name => $rowId) {
            if (str_starts_with($name, $needle) || str_starts_with($needle, $name)) {
                $hits[] = $rowId;
            }
        }
        if (count($hits) === 1) {
            return $hits[0];
        }

        return null;
    }
}

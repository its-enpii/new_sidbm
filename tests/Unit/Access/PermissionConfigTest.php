<?php

declare(strict_types=1);

namespace Tests\Unit\Access;

use App\Http\Requests\Accounting\JournalEntryRequest;
use App\Http\Requests\Accounting\LoanInstallmentJournalRequest;
use Tests\TestCase;

final class PermissionConfigTest extends TestCase
{
    public function test_permission_catalog_is_non_empty(): void
    {
        $perms = config('permissions.permissions');
        self::assertIsArray($perms);
        self::assertContains('journals.create', $perms);
        self::assertContains('installments.record', $perms);
        self::assertContains('messages.send', $perms);
        self::assertContains('assistant.use', $perms);
    }

    public function test_system_roles_cover_admin_and_kasir(): void
    {
        $roles = config('permissions.roles');
        self::assertArrayHasKey('admin', $roles);
        self::assertSame(['*'], $roles['admin']['permissions']);
        self::assertContains('installments.record', $roles['kasir']['permissions']);
        self::assertNotContains('loans.approve', $roles['kasir']['permissions']);
    }

    public function test_request_and_tool_maps_point_to_known_permissions(): void
    {
        $catalog = config('permissions.permissions');
        foreach (config('permissions.request_map') as $permission) {
            self::assertContains($permission, $catalog, "unknown permission in request_map: {$permission}");
        }
        foreach (config('permissions.tool_map') as $tool => $permission) {
            self::assertContains($permission, $catalog, "unknown permission for tool {$tool}");
        }

        self::assertSame('journals.create', config('permissions.request_map.'.JournalEntryRequest::class));
        self::assertSame('installments.record', config('permissions.request_map.'.LoanInstallmentJournalRequest::class));
        self::assertSame('journals.create', config('permissions.tool_map.create_journal_entry'));
    }
}

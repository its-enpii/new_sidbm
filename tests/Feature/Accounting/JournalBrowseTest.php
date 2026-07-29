<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Accounting\Services\JournalReversalService;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class JournalBrowseTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private Account $cash;

    private Account $equity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Kasir Browse',
            'email' => 'browse@example.test',
            'username' => 'browse_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        $this->cash = Account::query()->create([
            'code' => '1.1.01',
            'name' => 'Kas',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 3,
            'is_postable' => true,
            'is_active' => true,
        ]);
        $this->equity = Account::query()->create([
            'code' => '3.1.01',
            'name' => 'Modal',
            'account_type' => 'equity',
            'normal_balance' => 'C',
            'level' => 3,
            'is_postable' => true,
            'is_active' => true,
        ]);

        FiscalPeriod::query()->create([
            'fiscal_year' => 2026,
            'fiscal_month' => 7,
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-07-31',
            'status' => 'open',
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_index_lists_posted_journals(): void
    {
        $entry = $this->postedManual(100000, 'Setoran modal uji');

        $this->actingAs($this->user)
            ->get('/accounting/journals?from=2026-07-01&to=2026-07-31')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/Journals/Index')
                ->has('rows', 1)
                ->where('rows.0.id', $entry->id)
                ->where('rows.0.can_reverse', true)
            );
    }

    public function test_reverse_creates_counter_entry_and_blocks_second_reverse(): void
    {
        $entry = $this->postedManual(50000, 'Jurnal salah');

        $this->actingAs($this->user)
            ->post('/accounting/journals/'.$entry->row_id.'/reverse', [
                'reversal_date' => '2026-07-20',
                'reason' => 'Salah nominal',
            ])
            ->assertRedirect();

        $reversal = JournalEntry::query()
            ->where('reversed_entry_row_id', $entry->row_id)
            ->first();
        self::assertNotNull($reversal);
        self::assertSame('posted', $reversal->status);
        self::assertSame('journal_reversal', $reversal->source_type);

        $this->actingAs($this->user)
            ->post('/accounting/journals/'.$entry->row_id.'/reverse', [
                'reversal_date' => '2026-07-21',
                'reason' => 'Coba lagi',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_service_reverse_also_works_standalone(): void
    {
        $entry = $this->postedManual(25000, 'Standalone reverse');
        $rev = app(JournalReversalService::class)->reverse($entry, '2026-07-22', (int) $this->user->row_id, 'test');
        self::assertSame('posted', $rev->status);
        self::assertSame((int) $entry->row_id, (int) $rev->reversed_entry_row_id);
    }

    private function postedManual(float $amount, string $description): JournalEntry
    {
        $entry = JournalEntry::query()->create([
            'transaction_date' => '2026-07-18',
            'sequence_number' => 1,
            'source_type' => 'manual',
            'description' => $description,
            'status' => 'draft',
            'created_by_user_id' => $this->user->row_id,
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 1,
            'account_row_id' => $this->cash->row_id,
            'debit' => number_format($amount, 2, '.', ''),
            'credit' => '0.00',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 2,
            'account_row_id' => $this->equity->row_id,
            'debit' => '0.00',
            'credit' => number_format($amount, 2, '.', ''),
        ]);

        return app(JournalPostingService::class)->post($entry, (int) $this->user->row_id);
    }
}

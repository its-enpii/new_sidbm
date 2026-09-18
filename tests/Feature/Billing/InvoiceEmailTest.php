<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Mail\BillingInvoiceMail;
use App\Mail\UpcomingNotificationMail;
use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Invoice;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\Platform\TenantPlacement;
use App\Models\User;
use App\Services\Billing\InvoiceEmailService;
use App\Services\Notifications\EmailNotificationService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

final class InvoiceEmailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        Artisan::call('migrate:fresh', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);
    }

    public function test_tenant_invoice_is_delivered_to_billing_recipient(): void
    {
        [$user, $tenant] = $this->createTenantWithUser();
        $invoice = $this->makeInvoice($tenant->row_id, 'INV-MAIL-1', 'issued');

        Mail::fake();

        $result = app(InvoiceEmailService::class)->sendInvoice($invoice);

        $this->assertTrue($result['success']);
        $this->assertEquals($user->email, $result['recipient']);

        Mail::assertSent(BillingInvoiceMail::class, fn ($mail) => $mail->invoice->row_id === $invoice->row_id);
    }

    public function test_invoice_email_records_delivery_metadata(): void
    {
        [, $tenant] = $this->createTenantWithUser();
        $invoice = $this->makeInvoice($tenant->row_id, 'INV-MAIL-2', 'issued');

        Mail::fake();

        app(InvoiceEmailService::class)->sendInvoice($invoice, 'explicit@example.test');

        $meta = $invoice->fresh()->metadata;
        $this->assertEquals('explicit@example.test', $meta['last_email_recipient']);
        $this->assertNotNull($meta['last_email_sent_at']);
    }

    public function test_invoice_without_recipient_returns_failure(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->makeInvoice($tenant->row_id, 'INV-MAIL-3', 'issued');

        Mail::fake();

        $result = app(InvoiceEmailService::class)->sendInvoice($invoice);

        $this->assertFalse($result['success']);
        $this->assertNull($result['recipient']);
        Mail::assertNotSent(BillingInvoiceMail::class);
    }

    public function test_user_opted_out_of_billing_email_is_skipped(): void
    {
        [, $tenant] = $this->createTenantWithUser(['email_notifications' => ['billing' => false]]);
        $invoice = $this->makeInvoice($tenant->row_id, 'INV-MAIL-4', 'issued');

        Mail::fake();

        $result = app(InvoiceEmailService::class)->sendInvoice($invoice);

        $this->assertFalse($result['success']);
        Mail::assertNotSent(BillingInvoiceMail::class);
    }

    public function test_tenant_user_can_trigger_send_email_endpoint(): void
    {
        [$user, $tenant] = $this->createTenantWithUser();
        $invoice = $this->makeInvoice($tenant->row_id, 'INV-MAIL-5', 'issued');

        Mail::fake();

        $this->actingAs($user)
            ->post("/billing/invoices/{$invoice->row_id}/send-email")
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(BillingInvoiceMail::class);
    }

    public function test_tenant_cannot_email_foreign_invoice(): void
    {
        [$user] = $this->createTenantWithUser();
        $other = $this->createTenant();
        $foreign = $this->makeInvoice($other->row_id, 'INV-MAIL-6', 'issued');

        Mail::fake();

        $this->actingAs($user)
            ->post("/billing/invoices/{$foreign->row_id}/send-email")
            ->assertNotFound();

        Mail::assertNotSent(BillingInvoiceMail::class);
    }

    public function test_superadmin_can_send_invoice_email(): void
    {
        [, $tenant] = $this->createTenantWithUser();
        $invoice = $this->makeInvoice($tenant->row_id, 'INV-MAIL-7', 'issued');
        $admin = $this->createUser(['is_superadmin' => true]);

        Mail::fake();

        $this->actingAs($admin)
            ->post("/admin/invoices/{$invoice->row_id}/send-email", ['recipient' => 'admin@example.test'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(BillingInvoiceMail::class, fn ($mail) => $mail->invoice->row_id === $invoice->row_id);
        $this->assertEquals('admin@example.test', $invoice->fresh()->metadata['last_email_recipient']);
    }

    public function test_superadmin_send_email_validates_recipient(): void
    {
        [, $tenant] = $this->createTenantWithUser();
        $invoice = $this->makeInvoice($tenant->row_id, 'INV-MAIL-8', 'issued');
        $admin = $this->createUser(['is_superadmin' => true]);

        Mail::fake();

        $this->actingAs($admin)
            ->post("/admin/invoices/{$invoice->row_id}/send-email", ['recipient' => 'not-an-email'])
            ->assertSessionHasErrors('recipient');

        Mail::assertNotSent(BillingInvoiceMail::class);
    }

    public function test_invoice_mail_renders_invoice_details(): void
    {
        [, $tenant] = $this->createTenantWithUser();
        $invoice = $this->makeInvoice($tenant->row_id, 'INV-MAIL-9', 'issued', ['amount' => 250000, 'purpose' => 'training']);

        Mail::fake();
        app(InvoiceEmailService::class)->sendInvoice($invoice, 'render@example.test');

        $rendered = (new BillingInvoiceMail($invoice))->render();

        $this->assertStringContainsString('INV-MAIL-9', $rendered);
        $this->assertStringContainsString('Rp 250.000', $rendered);
        $this->assertStringContainsString('Pelatihan', $rendered);
        $this->assertStringContainsString('Lihat &amp; Bayar Tagihan', $rendered);
    }

    public function test_upcoming_notification_is_delivered_to_opted_in_user(): void
    {
        [$user] = $this->createTenantWithUser();

        Mail::fake();

        $result = app(EmailNotificationService::class)->sendUpcoming(
            $user->fresh(),
            'Fitur Laporan Interaktif',
            'Lintas fitur pembaruan dashboard akan segera hadir.',
        );

        $this->assertTrue($result['success']);
        Mail::assertSent(UpcomingNotificationMail::class);
    }

    public function test_upcoming_notification_skips_opted_out_user(): void
    {
        [$user] = $this->createTenantWithUser(['email_notifications' => ['announcements' => false]]);

        Mail::fake();

        $result = app(EmailNotificationService::class)->sendUpcoming(
            $user->fresh(),
            'Fitur Laporan Interaktif',
            'Pembaruan akan segera hadir.',
        );

        $this->assertFalse($result['success']);
        Mail::assertNotSent(UpcomingNotificationMail::class);
    }

    public function test_upcoming_notification_mail_renders(): void
    {
        $rendered = (new UpcomingNotificationMail(
            title: 'Fitur Baru: E-Budgeting Cerdas',
            body: "Fitur ini akan segera hadir.\nSalam hangat.",
            actionUrl: 'https://example.test/fitur',
        ))->render();

        $this->assertStringContainsString('Fitur Baru: E-Budgeting Cerdas', $rendered);
        $this->assertStringContainsString('Segera Hadir', $rendered);
    }

    public function test_invoice_helpers_format_labels(): void
    {
        [, $tenant] = $this->createTenantWithUser();
        $invoice = $this->makeInvoice($tenant->row_id, 'INV-MAIL-10', 'overdue');

        $this->assertEquals('Terlambat', $invoice->statusLabel());
        $this->assertEquals('Langganan Aplikasi', $this->makeInvoice($tenant->row_id, 'X', 'issued', ['purpose' => 'subscription'])->purposeLabel());
        $this->assertStringContainsString('/billing/invoices/', $invoice->onlineUrl());
    }

    private function makeInvoice(int $tenantId, string $number, string $status, array $attributes = []): Invoice
    {
        return Invoice::query()->create(array_merge([
            'public_id' => (string) Str::ulid(),
            'number' => $number,
            'tenant_id' => $tenantId,
            'purpose' => 'training',
            'status' => $status,
            'amount' => 500000,
            'amount_paid' => 0,
            'currency' => 'IDR',
            'issued_at' => $status === 'draft' ? null : now(),
            'due_at' => now()->addDays(7)->toDateString(),
            'description' => 'Pelatihan pengguna aplikasi',
        ], $attributes));
    }

    private function createTenant(): Tenant
    {
        DatabaseShard::query()->firstOrCreate(
            ['code' => 'local'],
            [
                'public_id' => (string) Str::ulid(),
                'name' => 'Local Shard',
                'driver' => (string) config('database.connections.tenant.driver', 'sqlite'),
                'host' => 'sqlite',
                'port' => 3306,
                'database_name' => (string) config('database.connections.tenant.database'),
                'credential_reference' => 'local',
                'placement_type' => 'shared',
                'status' => 'active',
            ],
        );

        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => Str::lower(Str::random(8)),
            'name' => 'Billing Tenant',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'metadata' => ['domains' => ['localhost']],
        ]);

        TenantPlacement::query()->create([
            'tenant_id' => $tenant->row_id,
            'shard_id' => DatabaseShard::query()->where('code', 'local')->value('row_id'),
            'status' => 'active',
            'placed_at' => now(),
        ]);

        return $tenant;
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function createTenantWithUser(array $userAttributes = []): array
    {
        $tenant = $this->createTenant();

        $user = $this->createUser(array_merge(['tenant_id' => $tenant->row_id], $userAttributes));

        TenantMembership::query()->create([
            'tenant_id' => $tenant->row_id,
            'user_id' => $user->row_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        config(['tenancy.local_tenant' => $tenant->code]);

        return [$user, $tenant];
    }

    private function createUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'public_id' => (string) Str::ulid(),
            'name' => 'Billing User',
            'email' => Str::lower((string) Str::ulid()).'@example.test',
            'username' => 'mail_'.Str::lower(Str::random(10)),
            'password' => 'password',
            'status' => 'active',
        ], $attributes));
    }
}

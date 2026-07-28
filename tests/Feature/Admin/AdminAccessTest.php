<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Platform\Invoice;
use App\Models\Platform\Plan;
use App\Models\Platform\Tenant;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdminAccessTest extends TestCase
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

    public function test_non_superadmin_cannot_access_admin(): void
    {
        $user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'User',
            'username' => 'tenantuser',
            'email' => 'user@example.test',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_superadmin_can_open_admin_dashboard(): void
    {
        $this->actingAs($this->superadmin())
            ->get('/admin')
            ->assertOk();
    }

    public function test_manual_payment_marks_invoice_paid(): void
    {
        $admin = $this->superadmin();
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'demo',
            'name' => 'Demo Tenant',
            'status' => 'active',
        ]);
        $invoice = Invoice::query()->create([
            'public_id' => (string) Str::ulid(),
            'number' => 'INV-202607-00001',
            'tenant_id' => $tenant->row_id,
            'status' => 'issued',
            'amount' => 100000,
            'amount_paid' => 0,
            'currency' => 'IDR',
            'issued_at' => now(),
            'due_at' => now()->addDays(7)->toDateString(),
            'description' => 'Test',
            'created_by' => $admin->row_id,
        ]);

        $this->actingAs($admin)
            ->post("/admin/invoices/{$invoice->row_id}/payments/manual", [
                'amount' => 100000,
                'reference' => 'TRF-1',
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('100000.00', (string) $invoice->amount_paid);
    }

    public function test_plan_can_be_created(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)
            ->post('/admin/plans', [
                'code' => 'basic',
                'name' => 'Basic',
                'price_amount' => 150000,
                'currency' => 'IDR',
                'billing_period' => 'monthly',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.plans.index'));

        $this->assertTrue(Plan::query()->where('code', 'basic')->exists());
    }

    public function test_tripay_callback_rejects_bad_signature(): void
    {
        config([
            'tripay.private_key' => 'test-private',
            'tripay.merchant_code' => 'T',
            'tripay.api_key' => 'K',
        ]);

        $this->call(
            'POST',
            '/tripay/callback',
            ['merchant_ref' => 'x', 'status' => 'PAID', 'reference' => 'R1'],
            [],
            [],
            ['HTTP_X_CALLBACK_SIGNATURE' => 'invalid', 'CONTENT_TYPE' => 'application/json'],
            json_encode(['merchant_ref' => 'x', 'status' => 'PAID', 'reference' => 'R1']),
        )->assertStatus(403);
    }

    private function superadmin(): User
    {
        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Super Admin',
            'username' => 'superadmin_'.Str::lower(Str::random(6)),
            'email' => 'superadmin_'.Str::lower(Str::random(6)).'@example.test',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);
    }
}

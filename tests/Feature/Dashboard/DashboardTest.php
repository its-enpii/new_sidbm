<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DashboardTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_dashboard_without_sensitive_props(): void
    {
        $user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Dashboard User',
            'email' => 'dashboard@example.test',
            'username' => 'dashboard_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('summaryCards.0.value', null)
                ->where('transactions', [])
                ->where('upcomingDue', [])
                ->where('activities', [])
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token'));
    }
}

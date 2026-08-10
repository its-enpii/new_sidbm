<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Platform\Subscription;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureSubscriptionActive
{
    public function __construct(
        private TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->tenant();
        if ($tenant === null) {
            return $next($request);
        }

        // Jangan blokir rute billing, profile, auth, atau webhook
        if ($request->routeIs('billing.*') || $request->routeIs('profile.*') || $request->is('logout') || $request->is('api/*')) {
            return $next($request);
        }

        // Cek status subscription terkini
        $subscription = Subscription::query()
            ->where('tenant_id', $tenant->row_id)
            ->latest('row_id')
            ->first();

        if ($subscription !== null && in_array($subscription->status, ['suspended', 'past_due'], true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Langganan aplikasi Anda sedang ditangguhkan karena tagihan melewati jatuh tempo. Silakan lakukan pembayaran tagihan pada menu Tagihan/Billing.',
                    'status' => $subscription->status,
                ], 402);
            }

            return redirect()->route('billing.invoices.index')
                ->with('error', 'Akses fitur operasional dibatasi karena langganan Anda ditangguhkan/menunggak. Silakan selesaikan pembayaran tagihan untuk mengaktifkan kembali.');
        }

        return $next($request);
    }
}

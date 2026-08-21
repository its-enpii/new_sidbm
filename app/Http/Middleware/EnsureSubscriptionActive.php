<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Platform\Invoice;
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
        if ($request->routeIs('billing.*') || $request->routeIs('profile.*') || $request->is('logout') || $request->is('api/*') || $request->routeIs('admin.*')) {
            return $next($request);
        }

        // 1. Cek apakah ada invoice aktif yang memblokir akses (blocks_access = true)
        $blockingInvoice = Invoice::query()
            ->where('tenant_id', $tenant->row_id)
            ->where('blocks_access', true)
            ->whereIn('status', ['issued', 'partially_paid', 'overdue', 'pending_payment'])
            ->oldest('due_at')
            ->first();

        if ($blockingInvoice !== null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => "Akses fitur operasional ditangguhkan sementara karena tagihan #{$blockingInvoice->number} ({$blockingInvoice->description}) memblokir akses sampai diselesaikan.",
                    'invoice_id' => $blockingInvoice->row_id,
                    'status' => 'blocked',
                ], 402);
            }

            return redirect()->route('billing.invoices.show', $blockingInvoice->row_id)
                ->with('error', "Akses fitur operasional ditangguhkan karena tagihan #{$blockingInvoice->number} mewajibkan pelunasan sebelum dapat melanjutkan aktivitas. Silakan lakukan pembayaran tagihan.");
        }

        // 2. Cek status subscription terkini
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

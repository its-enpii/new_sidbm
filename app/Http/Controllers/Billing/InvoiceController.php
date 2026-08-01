<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Domain\Access\Services\PermissionChecker;
use App\Models\Platform\Invoice;
use App\Services\Billing\InvoicePaymentService;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class InvoiceController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
    ) {
    }

    public function index(Request $request, TenantContext $context): Response
    {
        $this->permissions->denyUnless($request->user(), 'billing.view');

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $perPage = in_array((int) $request->query('per_page'), [15, 30, 50, 100], true)
            ? (int) $request->query('per_page')
            : 15;
        $sort = in_array($request->query('sort'), ['number', 'due_at', 'amount', 'status', 'issued_at'], true)
            ? (string) $request->query('sort')
            : 'row_id';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $invoices = Invoice::query()
            ->where('tenant_id', $context->id())
            ->where('status', '!=', 'draft')
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('number', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Invoice $invoice): array => [
                'row_id' => $invoice->row_id,
                'number' => $invoice->number,
                'purpose' => $invoice->purpose,
                'status' => $invoice->status,
                'amount' => $invoice->amount,
                'amount_paid' => $invoice->amount_paid,
                'remaining' => $invoice->remainingAmount(),
                'currency' => $invoice->currency,
                'due_at' => $invoice->due_at?->toDateString(),
                'issued_at' => $invoice->issued_at?->toDateTimeString(),
                'description' => $invoice->description,
            ]);

        return Inertia::render('Billing/Invoices/Index', compact('invoices', 'search', 'status', 'perPage', 'sort', 'direction'));
    }

    public function show(Request $request, Invoice $invoice, TenantContext $context): Response
    {
        $this->permissions->denyUnless($request->user(), 'billing.view');
        $this->assertTenantOwns($invoice, $context);

        $invoice->load([
            'subscription.plan:row_id,code,name',
            'payments' => fn ($q) => $q->latest('row_id'),
        ]);

        return Inertia::render('Billing/Invoices/Show', [
            'invoice' => [
                'row_id' => $invoice->row_id,
                'public_id' => $invoice->public_id,
                'number' => $invoice->number,
                'purpose' => $invoice->purpose,
                'status' => $invoice->status,
                'amount' => $invoice->amount,
                'amount_paid' => $invoice->amount_paid,
                'remaining' => $invoice->remainingAmount(),
                'currency' => $invoice->currency,
                'description' => $invoice->description,
                'notes' => $invoice->notes,
                'issued_at' => $invoice->issued_at?->toDateTimeString(),
                'due_at' => $invoice->due_at?->toDateString(),
                'paid_at' => $invoice->paid_at?->toDateTimeString(),
                'subscription' => $invoice->subscription ? [
                    'row_id' => $invoice->subscription->row_id,
                    'status' => $invoice->subscription->status,
                    'plan' => $invoice->subscription->plan?->only(['code', 'name']),
                ] : null,
                'is_open' => $invoice->isOpen() && $invoice->status !== 'draft',
            ],
            'payments' => $invoice->payments->map(fn ($p) => [
                'row_id' => $p->row_id,
                'method' => $p->method,
                'status' => $p->status,
                'amount' => $p->amount,
                'paid_at' => $p->paid_at?->toDateTimeString(),
                'reference' => $p->reference,
                'tripay_reference' => $p->tripay_reference,
                'tripay_checkout_url' => $p->tripay_checkout_url,
                'notes' => $p->notes,
            ]),
        ]);
    }

    public function pay(Request $request, Invoice $invoice, TenantContext $context, InvoicePaymentService $payments): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'billing.pay');
        $this->assertTenantOwns($invoice, $context);

        try {
            $payment = $payments->initiateTripay($invoice, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $url = $payment->tripay_checkout_url;
        if (is_string($url) && $url !== '') {
            return redirect()->away($url);
        }

        return back()->with('warning', 'Transaksi dibuat, tetapi link checkout belum tersedia. Coba lagi sebentar.');
    }

    private function assertTenantOwns(Invoice $invoice, TenantContext $context): void
    {
        if ((int) $invoice->tenant_id !== $context->id() || $invoice->status === 'draft') {
            abort(404);
        }
    }
}

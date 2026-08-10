<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Domain\Access\Services\PermissionChecker;
use App\Models\Platform\Invoice;
use App\Models\Platform\InvoicePayment;
use App\Services\Billing\InvoicePaymentService;
use App\Services\Billing\TripayClient;
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
        private readonly TripayClient $tripayClient,
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

        $channels = $this->tripayClient->getPaymentChannels();

        // Extract active in-app payment details (if there's a pending Tripay payment)
        $activeTripayPayment = $invoice->payments->first(
            fn (InvoicePayment $p) => $p->method === 'tripay' && $p->status === 'pending',
        );

        $activePaymentDetails = null;
        if ($activeTripayPayment !== null) {
            $payload = $activeTripayPayment->tripay_payload ?? [];
            $activePaymentDetails = [
                'payment_id' => $activeTripayPayment->row_id,
                'reference' => $activeTripayPayment->tripay_reference,
                'method_code' => $payload['payment_method'] ?? 'QRIS2',
                'payment_name' => $payload['payment_name'] ?? 'Online Payment',
                'pay_code' => $payload['pay_code'] ?? null,
                'qr_url' => $payload['qr_url'] ?? null,
                'qr_string' => $payload['qr_string'] ?? null,
                'amount' => (int) ($payload['amount'] ?? $activeTripayPayment->amount),
                'total_amount' => (int) ($payload['amount'] ?? $activeTripayPayment->amount),
                'fee_customer' => (int) ($payload['fee_customer'] ?? 0),
                'expired_time' => isset($payload['expired_time']) ? date('Y-m-d H:i:s', (int) $payload['expired_time']) : null,
                'instructions' => $payload['instructions'] ?? [],
                'checkout_url' => $activeTripayPayment->tripay_checkout_url,
            ];
        }

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
            'channels' => $channels,
            'active_payment' => $activePaymentDetails,
            'payments' => $invoice->payments->map(fn ($p) => [
                'row_id' => $p->row_id,
                'method' => $p->method,
                'status' => $p->status,
                'amount' => $p->amount,
                'paid_at' => $p->paid_at?->toDateTimeString(),
                'reference' => $p->reference,
                'tripay_reference' => $p->tripay_reference,
                'tripay_checkout_url' => $p->tripay_checkout_url,
                'payment_name' => $p->tripay_payload['payment_name'] ?? null,
                'pay_code' => $p->tripay_payload['pay_code'] ?? null,
                'qr_url' => $p->tripay_payload['qr_url'] ?? null,
                'notes' => $p->notes,
            ]),
        ]);
    }

    public function pay(Request $request, Invoice $invoice, TenantContext $context, InvoicePaymentService $payments): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'billing.pay');
        $this->assertTenantOwns($invoice, $context);

        $paymentMethod = (string) $request->input('payment_method', 'QRIS2');

        try {
            $payment = $payments->initiateTripay($invoice, $request->user(), $paymentMethod);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Kode pembayaran berhasil dibuat. Silakan selesaikan pembayaran.');
    }

    public function checkStatus(Request $request, Invoice $invoice, TenantContext $context, InvoicePaymentService $payments): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'billing.pay');
        $this->assertTenantOwns($invoice, $context);

        $activeTripayPayment = $invoice->payments()->where('method', 'tripay')->where('status', 'pending')->latest('row_id')->first();
        if ($activeTripayPayment !== null) {
            $updated = $payments->checkAndSyncStatus($activeTripayPayment);
            if ($updated->status === 'paid') {
                return back()->with('success', 'Pembayaran berhasil dikonfirmasi! Langganan Anda telah aktif.');
            }
        }

        return back()->with('info', 'Status pembayaran belum berubah. Silakan lakukan pembayaran jika belum.');
    }

    private function assertTenantOwns(Invoice $invoice, TenantContext $context): void
    {
        if ((int) $invoice->tenant_id !== $context->id() || $invoice->status === 'draft') {
            abort(404);
        }
    }
}

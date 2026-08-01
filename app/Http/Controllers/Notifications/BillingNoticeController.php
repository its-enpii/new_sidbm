<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notifications;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Notifications\Services\WhatsappNotificationService;
use App\Services\WhatsappGatewayService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BillingNoticeController
{
    public function __construct(
        private readonly WhatsappNotificationService $notices,
        private readonly WhatsappGatewayService $gateway,
        private readonly PermissionChecker $permissions,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->permissions->denyUnless($request->user(), 'messages.send');

        $due = $this->resolveDueDate($request);
        $items = $this->notices->dueOn($due);
        $state = $this->gateway->isConfigured()
            ? $this->gateway->connectionState()
            : [
                'success' => false,
                'status' => 'unconfigured',
                'message' => 'Gateway WhatsApp belum dikonfigurasi.',
                'state' => null,
                'instance' => $this->gateway->getInstance(),
            ];

        return Inertia::render('Notifications/BillingNotice', [
            'due_date' => $due->toDateString(),
            'items' => $items,
            'gateway' => [
                'enabled' => $this->gateway->isEnabled(),
                'configured' => $this->gateway->isConfigured(),
                'instance' => $this->gateway->getInstance(),
                'state' => $state['state'] ?? null,
                'status_message' => $state['message'] ?? null,
            ],
            'totals' => [
                'count' => count($items),
                'amount' => array_sum(array_column($items, 'amount')),
                'with_phone' => count(array_filter($items, fn (array $i): bool => $i['can_send'])),
            ],
        ]);
    }

    public function send(Request $request, PermissionChecker $permissions): RedirectResponse
    {
        $permissions->denyUnless($request->user(), 'messages.send');

        $validated = $request->validate([
            'due_date' => ['required', 'date_format:Y-m-d'],
            'installment_row_ids' => ['required', 'array', 'min:1'],
            'installment_row_ids.*' => ['integer', 'min:1'],
        ]);

        if (! $this->gateway->isConfigured() || ! $this->gateway->isEnabled()) {
            return back()->with('error', 'WhatsApp gateway belum aktif/terkonfigurasi.');
        }

        $due = CarbonImmutable::createFromFormat('Y-m-d', $validated['due_date'])->startOfDay();
        $result = $this->notices->sendBilling(
            array_map('intval', $validated['installment_row_ids']),
            $due,
        );

        $message = sprintf(
            'Tagihan: %d terkirim, %d gagal, %d dilewati.',
            $result['sent'],
            $result['failed'],
            $result['skipped'],
        );

        if ($result['sent'] > 0 && $result['failed'] === 0) {
            return redirect()
                ->route('notifications.billing', ['due_date' => $due->toDateString()])
                ->with('success', $message);
        }

        return redirect()
            ->route('notifications.billing', ['due_date' => $due->toDateString()])
            ->with($result['sent'] > 0 ? 'warning' : 'error', $message);
    }

    private function resolveDueDate(Request $request): CarbonImmutable
    {
        $raw = $request->query('due_date');
        if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            try {
                return CarbonImmutable::createFromFormat('Y-m-d', $raw)->startOfDay();
            } catch (\Throwable) {
                // fall through
            }
        }

        return CarbonImmutable::today();
    }
}

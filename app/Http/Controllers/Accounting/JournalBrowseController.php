<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Services\JournalBrowseService;
use App\Domain\Accounting\Services\JournalReversalService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class JournalBrowseController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly JournalBrowseService $browse,
        private readonly JournalReversalService $reversals,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->permissions->denyUnless($request->user(), 'journals.view');

        $from = (string) $request->query('from', CarbonImmutable::today()->startOfMonth()->toDateString());
        $to = (string) $request->query('to', CarbonImmutable::today()->toDateString());
        $q = $request->query('q');
        $source = $request->query('source');
        $page = max(1, (int) $request->query('page', 1));

        $payload = $this->browse->list(
            from: $from,
            to: $to,
            q: is_string($q) ? $q : null,
            source: is_string($source) ? $source : null,
            page: $page,
        );

        return Inertia::render('Accounting/Journals/Index', [
            ...$payload,
            'sourceOptions' => [
                ['value' => 'all', 'label' => 'Semua sumber'],
                ['value' => 'loan_installment', 'label' => 'Angsuran'],
                ['value' => 'loan', 'label' => 'Pencairan'],
                ['value' => 'manual', 'label' => 'Jurnal umum'],
                ['value' => 'journal_reversal', 'label' => 'Reversal'],
                ['value' => 'loan_write_off', 'label' => 'Penghapusan'],
                ['value' => 'loan_reschedule_close', 'label' => 'Reschedule'],
                ['value' => 'profit_allocation', 'label' => 'Alokasi laba'],
            ],
            'can_reverse' => $this->permissions->allows($request->user(), 'journals.create'),
        ]);
    }

    public function reverse(Request $request, JournalEntry $entry): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'journals.create');

        $data = $request->validate([
            'reversal_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $reversal = $this->reversals->reverse(
                original: $entry,
                reversalDate: $data['reversal_date'],
                platformUserId: (int) $request->user()->row_id,
                reason: $data['reason'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            'Jurnal #'.$entry->id.' dibatalkan. Reversal #'.$reversal->id.' dibuat.',
        );
    }
}

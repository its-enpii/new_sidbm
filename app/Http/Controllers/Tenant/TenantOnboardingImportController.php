<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Onboarding\Services\TenantOnboardingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TenantOnboardingImportController extends Controller
{
    public function index(Request $request): Response
    {
        $accounts = Account::query()
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'account_type']);

        $existingOpening = JournalEntry::query()
            ->where('reference', 'like', 'SALDO-AWAL-%')
            ->with(['lines.account:row_id,code,name'])
            ->latest('row_id')
            ->first();

        return Inertia::render('Onboarding/ImportWizard', [
            'accounts' => $accounts,
            'existingOpening' => $existingOpening ? [
                'row_id' => $existingOpening->row_id,
                'as_of_date' => $existingOpening->transaction_date?->toDateString(),
                'reference' => $existingOpening->reference,
                'amount' => (float) $existingOpening->amount,
                'lines' => $existingOpening->lines->map(fn ($l) => [
                    'account_row_id' => $l->account_row_id,
                    'account_code' => $l->account?->code,
                    'account_name' => $l->account?->name,
                    'debit' => (float) $l->debit,
                    'credit' => (float) $l->credit,
                ]),
            ] : null,
        ]);
    }

    public function saveOpeningBalances(Request $request, TenantOnboardingService $service): RedirectResponse
    {
        $validated = $request->validate([
            'as_of_date' => ['required', 'date', 'before_or_equal:today'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_row_id' => ['required', 'integer'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
        ]);

        $userId = (int) $request->user()->row_id;
        $service->saveOpeningBalances($validated['lines'], (string) $validated['as_of_date'], $userId);

        return redirect()->back()->with('success', 'Jurnal Saldo Awal Keuangan tenant berhasil disimpan & diposting.');
    }

    public function importActiveLoans(Request $request, TenantOnboardingService $service): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $userId = (int) $request->user()->row_id;
        $result = $service->importActiveLoans($request->file('file'), $userId);

        $msg = sprintf('Berhasil mengimpor %d pinjaman aktif.', $result['imported']);
        if ($result['skipped'] > 0) {
            $msg .= sprintf(' (%d dilewati)', $result['skipped']);
        }

        $redirect = redirect()->back()->with('success', $msg);
        if ($result['errors'] !== []) {
            $redirect->with('warning', 'Beberapa baris memiliki kesalahan: ' . implode(' | ', array_slice($result['errors'], 0, 5)));
        }

        return $redirect;
    }

    public function downloadTemplate(string $type, TenantOnboardingService $service): StreamedResponse
    {
        return $service->downloadCsvTemplate($type);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Services\FiscalPeriodCloseService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PeriodCloseController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly FiscalPeriodCloseService $closer,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->permissions->denyUnless($request->user(), 'journals.view');

        $year = (int) $request->query('year', CarbonImmutable::today()->year);
        if ($year < 2000 || $year > 2100) {
            $year = (int) CarbonImmutable::today()->year;
        }

        $payload = $this->closer->overview($year);

        return Inertia::render('Accounting/PeriodClose/Index', [
            ...$payload,
            'can_close' => $this->permissions->allows($request->user(), 'journals.create'),
            'year_options' => $this->yearOptions($year),
        ]);
    }

    public function closeMonth(Request $request, int $year, int $month): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'journals.create');

        try {
            $this->closer->closeMonth($year, $month, (int) $request->user()->row_id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('accounting.period-close.index', ['year' => $year])
            ->with('success', sprintf('Periode %02d/%d ditutup.', $month, $year));
    }

    public function reopenMonth(Request $request, int $year, int $month): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'journals.create');

        try {
            $this->closer->reopenMonth($year, $month);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('accounting.period-close.index', ['year' => $year])
            ->with('success', sprintf('Periode %02d/%d dibuka kembali.', $month, $year));
    }

    public function closeYear(Request $request): RedirectResponse
    {
        $this->permissions->denyUnless($request->user(), 'journals.create');

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'force' => ['sometimes', 'boolean'],
        ]);

        $year = (int) $validated['year'];
        $force = (bool) ($validated['force'] ?? false);

        try {
            $result = $this->closer->closeYear($year, (int) $request->user()->row_id, $force);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('accounting.period-close.index', ['year' => $year])
            ->with('success', sprintf(
                'Tutup tahun %d selesai. Bulan ditutup: %d. Saldo awal %d: %d akun. Laba/rugi: %s.',
                $year,
                $result['closed_months'],
                $result['next_year'],
                $result['openings_written'],
                number_format($result['net_income'], 0, ',', '.'),
            ));
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function yearOptions(int $current): array
    {
        $opts = [];
        for ($y = $current + 1; $y >= $current - 8; $y--) {
            $opts[] = ['value' => $y, 'label' => (string) $y];
        }

        return $opts;
    }
}

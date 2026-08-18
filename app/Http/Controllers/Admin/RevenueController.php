<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Platform\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class RevenueController
{
    public function __invoke(Request $request): Response
    {
        $year = (int) $request->query('year', (string) now()->year);
        $availableYears = Invoice::query()
            ->selectRaw('DISTINCT YEAR(issued_at) as y')
            ->whereNotNull('issued_at')
            ->orderByDesc('y')
            ->pluck('y')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->toArray();

        if (! in_array($year, $availableYears, true) && count($availableYears)) {
            $year = $availableYears[0];
        }

        if (! count($availableYears)) {
            $availableYears = [(int) now()->year];
        }

        $purposeFilter = (string) $request->query('purpose', '');

        $monthlyQuery = Invoice::query()
            ->select(
                DB::raw('MONTH(issued_at) as bulan'),
                DB::raw('COUNT(*) as total_invoice'),
                DB::raw('SUM(amount) as total_tagihan'),
                DB::raw('SUM(amount_paid) as total_terbayar'),
                DB::raw("SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as lunas"),
                DB::raw("SUM(CASE WHEN status IN ('issued','partially_paid','overdue') THEN 1 ELSE 0 END) as belum_lunas"),
            )
            ->whereYear('issued_at', $year)
            ->where('status', '!=', 'void')
            ->when($purposeFilter !== '', fn ($q) => $q->where('purpose', $purposeFilter))
            ->groupByRaw('MONTH(issued_at)')
            ->orderByRaw('MONTH(issued_at)')
            ->get();

        $months = collect(range(1, 12))->map(function (int $m) use ($monthlyQuery) {
            $row = $monthlyQuery->firstWhere('bulan', $m);

            return [
                'bulan' => $m,
                'label' => self::monthLabel($m),
                'total_invoice' => (int) ($row->total_invoice ?? 0),
                'total_tagihan' => (float) ($row->total_tagihan ?? 0),
                'total_terbayar' => (float) ($row->total_terbayar ?? 0),
                'lunas' => (int) ($row->lunas ?? 0),
                'belum_lunas' => (int) ($row->belum_lunas ?? 0),
            ];
        });

        $summary = [
            'total_invoice' => $months->sum('total_invoice'),
            'total_tagihan' => $months->sum('total_tagihan'),
            'total_terbayar' => $months->sum('total_terbayar'),
            'outstanding' => $months->sum('total_tagihan') - $months->sum('total_terbayar'),
            'lunas' => $months->sum('lunas'),
            'belum_lunas' => $months->sum('belum_lunas'),
        ];

        $byPurpose = Invoice::query()
            ->select(
                'purpose',
                DB::raw('COUNT(*) as jumlah'),
                DB::raw('SUM(amount) as total_tagihan'),
                DB::raw('SUM(amount_paid) as total_terbayar'),
            )
            ->whereYear('issued_at', $year)
            ->where('status', '!=', 'void')
            ->groupBy('purpose')
            ->orderByDesc('total_tagihan')
            ->get()
            ->map(fn ($row) => [
                'purpose' => $row->purpose,
                'jumlah' => (int) $row->jumlah,
                'total_tagihan' => (float) $row->total_tagihan,
                'total_terbayar' => (float) $row->total_terbayar,
            ]);

        return Inertia::render('Admin/Revenue/Index', [
            'year' => $year,
            'availableYears' => $availableYears,
            'purposeFilter' => $purposeFilter,
            'months' => $months->values(),
            'summary' => $summary,
            'byPurpose' => $byPurpose,
        ]);
    }

    private static function monthLabel(int $m): string
    {
        return ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][$m - 1];
    }
}

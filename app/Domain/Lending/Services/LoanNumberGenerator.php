<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services;

use App\Domain\Lending\Models\Loan;
use App\Models\Platform\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LoanNumberGenerator
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * Generate format: {seq}/SPK/{kode-kecamatan}/{MM}/{YYYY}
     * - MM/YYYY dari tanggal pencairan (atau fallback funded_at / approved_at / today)
     * - kode-kecamatan dari Tenant->district_code (fallback '000' bila kosong)
     * - seq = 3 digit (min 001) = jumlah pinjaman tenant dengan loan_number LIKE %/{MM}/{YYYY} + 1
     * - Atomic & idempotent dengan lock / retry max 5 kali
     */
    public function next(Loan $loan, ?string $disbursedDate = null): string
    {
        $tenantId = $this->tenantContext->id();
        $dateStr = $disbursedDate
            ?? $loan->disbursed_at?->toDateString()
            ?? $loan->funded_at?->toDateString()
            ?? $loan->approved_at?->toDateString()
            ?? date('Y-m-d');

        $date = CarbonImmutable::parse($dateStr);
        $mm = $date->format('m');
        $yyyy = $date->format('Y');

        $districtCode = '000';
        try {
            $tenant = $this->tenantContext->tenant();
            $code = trim((string) ($tenant->district_code ?? ''));
            if ($code !== '') {
                $districtCode = $code;
            }
        } catch (\Throwable) {
            $tenant = Tenant::query()->find($tenantId);
            $code = trim((string) ($tenant?->district_code ?? ''));
            if ($code !== '') {
                $districtCode = $code;
            }
        }

        $pattern = "%/{$mm}/{$yyyy}";

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $count = DB::connection('tenant')
                ->table('loans')
                ->where('tenant_id', $tenantId)
                ->where('loan_number', 'like', $pattern)
                ->where('row_id', '!=', $loan->row_id)
                ->lockForUpdate()
                ->count();

            $seq = str_pad((string) ($count + 1 + $attempt), 3, '0', STR_PAD_LEFT);
            $candidate = "{$seq}/SPK/{$districtCode}/{$mm}/{$yyyy}";

            $exists = DB::connection('tenant')
                ->table('loans')
                ->where('tenant_id', $tenantId)
                ->where('loan_number', $candidate)
                ->where('row_id', '!=', $loan->row_id)
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        throw new RuntimeException('Gagal menghasilkan nomor SPK unik setelah 5 percobaan.');
    }
}

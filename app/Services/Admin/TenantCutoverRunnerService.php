<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Platform\CutoverRun;
use App\Models\Platform\Tenant;
use Illuminate\Support\Facades\Artisan;
use Throwable;

final class TenantCutoverRunnerService
{
    public function execute(CutoverRun $run): void
    {
        $run->update([
            'status' => 'running',
            'started_at' => now(),
            'output_log' => '',
            'error_message' => null,
        ]);

        try {
            $tenant = Tenant::query()->where('row_id', $run->tenant_id)->first();
            if ($tenant === null) {
                throw new \RuntimeException("Tenant ID {$run->tenant_id} tidak ditemukan.");
            }

            $options = $run->options ?? [];
            $dryRun = (bool) ($run->is_dry_run ?? false);
            $chunk = (int) ($options['chunk'] ?? 500);
            $noFailFast = (bool) ($options['no_fail_fast'] ?? false);
            $continueOnError = (bool) ($options['continue_on_error'] ?? false);
            $fromYear = (int) ($options['from_year'] ?? 2018);
            $toYear = (int) ($options['to_year'] ?? (int) date('Y'));

            $stepsDef = $this->buildSteps(
                tenantCode: (string) $tenant->code,
                suffix: (string) $run->suffix,
                dryRun: $dryRun,
                chunk: $chunk,
                noFailFast: $noFailFast,
                fromYear: $fromYear,
                toYear: $toYear,
                options: $options,
            );

            $stepTracker = array_map(static fn (array $s): array => [
                'name' => $s['name'],
                'label' => $s['label'],
                'command' => $s['command'],
                'status' => $s['skip'] ? 'skipped' : 'pending',
                'exit' => null,
            ], $stepsDef);

            $run->update(['steps' => $stepTracker]);

            $logBuffer = sprintf(
                "=== MEMULAI CUTOVER DATA TENANT ===\nTenant: %s (ID: %d)\nSuffix Lokasi: %s\nMode Dry-Run: %s\nTanggal: %s\n\n",
                $tenant->code,
                $tenant->row_id,
                $run->suffix,
                $dryRun ? 'YA' : 'TIDAK',
                now()->toDateTimeString(),
            );

            $failed = false;

            foreach ($stepsDef as $index => $step) {
                if ($step['skip']) {
                    $logBuffer .= sprintf("[SKIP] %s (%s)\n", $step['label'], $step['name']);
                    $run->update(['output_log' => $logBuffer]);

                    continue;
                }

                $logBuffer .= sprintf(">>> Executing: %s...\n", $step['label']);
                $stepTracker[$index]['status'] = 'running';
                $run->update([
                    'steps' => $stepTracker,
                    'output_log' => $logBuffer,
                ]);

                $exitCode = Artisan::call($step['command'], $step['params']);
                $output = trim(Artisan::output());

                if ($output !== '') {
                    $logBuffer .= $output."\n";
                }

                $ok = ($exitCode === 0);
                $stepTracker[$index]['status'] = $ok ? 'ok' : 'failed';
                $stepTracker[$index]['exit'] = $exitCode;

                if (! $ok) {
                    $logBuffer .= sprintf("<<< FAILED: %s (Exit Code: %d)\n\n", $step['label'], $exitCode);
                    $failed = true;
                    $run->update([
                        'steps' => $stepTracker,
                        'output_log' => $logBuffer,
                    ]);

                    if (! $continueOnError) {
                        break;
                    }
                } else {
                    $logBuffer .= sprintf("<<< OK: %s\n\n", $step['label']);
                    $run->update([
                        'steps' => $stepTracker,
                        'output_log' => $logBuffer,
                    ]);
                }
            }

            $logBuffer .= sprintf(
                "=== SELESAI CUTOVER DATA ===\nStatus: %s\nWaktu Selesai: %s\n",
                $failed ? 'GAGAL' : 'BERHASIL',
                now()->toDateTimeString(),
            );

            $run->update([
                'status' => $failed ? 'failed' : 'completed',
                'completed_at' => now(),
                'output_log' => $logBuffer,
                'error_message' => $failed ? 'Beberapa step migrasi mengalami kegagalan. Cek log detail.' : null,
            ]);
        } catch (Throwable $e) {
            $run->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
                'output_log' => ($run->output_log ?? '')."\n[ERROR EXCEPTION] ".$e->getMessage()."\n".$e->getTraceAsString(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array{name: string, label: string, command: string, params: array<string, mixed>, skip: bool}>
     */
    private function buildSteps(
        string $tenantCode,
        string $suffix,
        bool $dryRun,
        int $chunk,
        bool $noFailFast,
        int $fromYear,
        int $toYear,
        array $options,
    ): array {
        $migrateFlags = ['--chunk' => $chunk];
        if ($fromYear > 0) {
            $migrateFlags['--from-date'] = sprintf('%04d-01-01', $fromYear);
        }
        if ($toYear > 0) {
            $migrateFlags['--to-date'] = sprintf('%04d-12-31', $toYear);
        }
        if ($dryRun) {
            $migrateFlags['--dry-run'] = true;
        }
        if ($noFailFast) {
            $migrateFlags['--no-fail-fast'] = true;
        }

        return [
            [
                'name' => 'fiscal-periods',
                'label' => 'Menyiapkan Periode Fiskal (Ensure Fiscal Periods)',
                'command' => 'legacy:ensure-fiscal-periods',
                'params' => [
                    'tenant' => $tenantCode,
                    '--suffix' => $suffix,
                    '--from' => $fromYear,
                    '--to' => $toYear,
                ],
                'skip' => ! empty($options['skip_fiscal']) || $dryRun,
            ],
            [
                'name' => 'chart-of-accounts',
                'label' => 'Import Bagan Akun COA Legacy',
                'command' => 'tenancy:import-legacy-chart-of-accounts',
                'params' => ['tenant' => $tenantCode],
                'skip' => ! empty($options['skip_coa']) || $dryRun,
            ],
            [
                'name' => 'accounting',
                'label' => 'Migrasi Akuntansi & Jurnal Umum',
                'command' => 'legacy:migrate-accounting',
                'params' => array_merge(['tenant' => $tenantCode,
                    '--suffix' => $suffix, 'suffix' => $suffix], $migrateFlags),
                'skip' => ! empty($options['skip_accounting']),
            ],
            [
                'name' => 'membership',
                'label' => 'Migrasi Data Keanggotaan & Kelompok',
                'command' => 'legacy:migrate-membership',
                'params' => array_merge(['tenant' => $tenantCode,
                    '--suffix' => $suffix, 'suffix' => $suffix], $migrateFlags),
                'skip' => ! empty($options['skip_membership']),
            ],
            [
                'name' => 'lending',
                'label' => 'Migrasi Data Pinjaman & Spk',
                'command' => 'legacy:migrate-lending',
                'params' => array_merge(['tenant' => $tenantCode,
                    '--suffix' => $suffix, 'suffix' => $suffix], $migrateFlags),
                'skip' => ! empty($options['skip_lending']),
            ],
            [
                'name' => 'loan-payment-progress',
                'label' => 'Pembaruan Progress Realisasi Angsuran',
                'command' => 'legacy:apply-loan-payment-progress',
                'params' => ['tenant' => $tenantCode],
                'skip' => ! empty($options['skip_payment_progress']) || $dryRun,
            ],
            [
                'name' => 'reconcile-lending',
                'label' => 'Rekonsiliasi Pinjaman Legacy vs Next',
                'command' => 'legacy:reconcile-lending',
                'params' => ['tenant' => $tenantCode,
                    '--suffix' => $suffix, 'suffix' => $suffix],
                'skip' => ! empty($options['skip_reconcile']) || $dryRun,
            ],
            [
                'name' => 'initialize-sequences',
                'label' => 'Inisialisasi Sequence / Nomor Otomatis',
                'command' => 'tenancy:initialize-sequences',
                'params' => ['tenant' => $tenantCode],
                'skip' => ! empty($options['skip_sequences']) || $dryRun,
            ],
        ];
    }
}

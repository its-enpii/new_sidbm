<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Migration\Accounting\LegacyAccountingDiscovery;
use App\Http\Controllers\Controller;
use App\Jobs\RunTenantCutoverJob;
use App\Models\Platform\CutoverRun;
use App\Models\Platform\Tenant;
use App\Services\Admin\TenantCutoverRunnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MigrationController extends Controller
{
    /**
     * Cache TTL for legacy suffix discovery (seconds). Discovery runs MIN/MAX
     * queries against the remote legacy MySQL across all suffixes — too slow
     * to call on every page render, so we cache the result for 5 minutes.
     */
    private const DISCOVERY_CACHE_TTL = 300;

    public function index(Request $request): Response
    {
        $tenants = Tenant::query()
            ->select(['row_id', 'code', 'name', 'status'])
            ->orderBy('name')
            ->get();

        // Discovery is intentionally NOT called here — it can take 60+ seconds
        // against a remote legacy MySQL. The Vue page loads instantly and
        // triggers /admin/migration/discover via AJAX (cached for 5 min).
        $discoveredSuffixes = [];

        $runs = CutoverRun::query()
            ->with(['tenant:row_id,code,name'])
            ->orderByDesc('id')
            ->paginate(15)
            ->through(fn ($run) => [
                'id' => $run->id,
                'tenant_id' => $run->tenant_id,
                'tenant_code' => $run->tenant_code,
                'tenant_name' => $run->tenant?->name ?? $run->tenant_code,
                'suffix' => $run->suffix,
                'is_dry_run' => $run->is_dry_run,
                'options' => $run->options,
                'status' => $run->status,
                'steps' => $run->steps,
                'error_message' => $run->error_message,
                'output_log' => $run->output_log,
                'started_at' => $run->started_at?->toIso8601String(),
                'completed_at' => $run->completed_at?->toIso8601String(),
                'created_at' => $run->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Migration/Index', [
            'tenants' => $tenants,
            'runs' => $runs,
            'legacy_config' => [
                'host' => (string) config('database.connections.legacy.host', '127.0.0.1'),
                'port' => (int) config('database.connections.legacy.port', 3306),
                'database' => (string) config('database.connections.legacy.database', 'sidbm'),
            ],
            'discovered_suffixes' => $discoveredSuffixes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:platform.tenants,row_id'],
            'suffix' => ['required', 'numeric'],
            'is_dry_run' => ['boolean'],
            'chunk' => ['nullable', 'integer', 'min:10', 'max:5000'],
            'from_year' => ['nullable', 'integer', 'min:2000'],
            'to_year' => ['nullable', 'integer', 'min:2000'],
            'skip_fiscal' => ['boolean'],
            'skip_coa' => ['boolean'],
            'skip_accounting' => ['boolean'],
            'skip_membership' => ['boolean'],
            'skip_lending' => ['boolean'],
            'skip_payment_progress' => ['boolean'],
            'skip_reconcile' => ['boolean'],
            'skip_sequences' => ['boolean'],
            'continue_on_error' => ['boolean'],
            'no_fail_fast' => ['boolean'],
            'run_immediately' => ['boolean'],
        ]);

        $tenant = Tenant::query()->where('row_id', (int) $validated['tenant_id'])->firstOrFail();

        $run = CutoverRun::query()->create([
            'tenant_id' => $tenant->row_id,
            'tenant_code' => $tenant->code,
            'suffix' => (string) $validated['suffix'],
            'is_dry_run' => (bool) ($validated['is_dry_run'] ?? false),
            'options' => [
                'chunk' => (int) ($validated['chunk'] ?? 500),
                'from_year' => (int) ($validated['from_year'] ?? 2018),
                'to_year' => (int) ($validated['to_year'] ?? (int) date('Y')),
                'skip_fiscal' => (bool) ($validated['skip_fiscal'] ?? false),
                'skip_coa' => (bool) ($validated['skip_coa'] ?? false),
                'skip_accounting' => (bool) ($validated['skip_accounting'] ?? false),
                'skip_membership' => (bool) ($validated['skip_membership'] ?? false),
                'skip_lending' => (bool) ($validated['skip_lending'] ?? false),
                'skip_payment_progress' => (bool) ($validated['skip_payment_progress'] ?? false),
                'skip_reconcile' => (bool) ($validated['skip_reconcile'] ?? false),
                'skip_sequences' => (bool) ($validated['skip_sequences'] ?? false),
                'continue_on_error' => (bool) ($validated['continue_on_error'] ?? false),
                'no_fail_fast' => (bool) ($validated['no_fail_fast'] ?? false),
            ],
            'status' => 'pending',
        ]);

        if (! empty($validated['run_immediately'])) {
            // Run synchronously for instant feedback in dev/testing
            app(TenantCutoverRunnerService::class)->execute($run);
        } else {
            RunTenantCutoverJob::dispatch($run);
        }

        return redirect()->back()->with('success', sprintf(
            'Proses migrasi data untuk tenant "%s" (suffix: %s) berhasil didaftarkan.',
            $tenant->name,
            $validated['suffix'],
        ));
    }

    public function stream(CutoverRun $run, TenantCutoverRunnerService $runner): StreamedResponse
    {
        return response()->stream(function () use ($run, $runner): void {
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');
            if (function_exists('ob_implicit_flush')) {
                ob_implicit_flush(true);
            }

            $runner->observeStream($run, static function (string $event, array $data): void {
                echo "event: {$event}\n";
                echo 'data: '.json_encode($data, JSON_THROW_ON_ERROR)."\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            });
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function show(CutoverRun $run): JsonResponse
    {
        $run->load('tenant:row_id,code,name');

        return response()->json([
            'id' => $run->id,
            'tenant_id' => $run->tenant_id,
            'tenant_code' => $run->tenant_code,
            'tenant_name' => $run->tenant?->name ?? $run->tenant_code,
            'suffix' => $run->suffix,
            'is_dry_run' => $run->is_dry_run,
            'status' => $run->status,
            'steps' => $run->steps,
            'error_message' => $run->error_message,
            'output_log' => $run->output_log,
            'started_at' => $run->started_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'created_at' => $run->created_at?->toIso8601String(),
        ]);
    }

    /**
     * AJAX endpoint that returns the cached legacy suffix discovery.
     * The first call scans the remote MySQL (slow), subsequent calls hit the cache.
     */
    public function discover(Request $request, LegacyAccountingDiscovery $discovery): JsonResponse
    {
        $force = $request->boolean('refresh');

        $cacheKey = 'admin.migration.discovery.v1';

        if ($force) {
            Cache::forget($cacheKey);
        }

        try {
            $suffixes = Cache::remember(
                $cacheKey,
                self::DISCOVERY_CACHE_TTL,
                fn () => $discovery->discover(),
            );
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'suffixes' => [],
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'count' => count($suffixes),
            'cached_until' => now()->addSeconds(self::DISCOVERY_CACHE_TTL)->toIso8601String(),
            'suffixes' => $suffixes,
        ]);
    }
}

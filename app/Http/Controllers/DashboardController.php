<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Dashboard\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController
{
    private const PIPELINE_MODAL_KEYS = ['proposal', 'verifikasi', 'waiting', 'aktif'];

    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $membership = $user?->memberships()
            ->with('tenant')
            ->where('status', 'active')
            ->orderBy('row_id')
            ->first();

        $payload = $this->dashboard->build();

        $pipelineKey = $this->resolvePipelineKey($request);
        $pipelineModal = $pipelineKey !== null
            ? $this->dashboard->loansByStatus($pipelineKey)
            : null;

        return Inertia::render('Dashboard', [
            'unitName' => $membership?->tenant?->name ?? $payload['unit_name'],
            ...$payload,
            'pipeline_modal' => $pipelineModal,
            'pipeline_modal_key' => $pipelineKey,
        ]);
    }

    private function resolvePipelineKey(Request $request): ?string
    {
        $key = (string) $request->query('pipeline', '');
        if ($key === '' || ! in_array($key, self::PIPELINE_MODAL_KEYS, true)) {
            return null;
        }

        return $key;
    }
}

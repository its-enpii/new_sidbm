<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Domain\Access\Services\PermissionChecker;
use App\Models\User;
use App\Notifications\AiAccessRequestNotification;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

final class AssistantAccessRequestController
{
    public function store(Request $request, TenantContext $context, PermissionChecker $permissionChecker): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $userId = (int) $user->row_id;
        $rateKey = 'ai_access_req:'.$userId.':'.now()->format('Y-m-d');

        if (RateLimiter::tooManyAttempts($rateKey, 1)) {
            return back()->with('info', 'Permintaan sudah terkirim hari ini. Mohon menunggu konfirmasi administrator Anda.');
        }

        RateLimiter::hit($rateKey, 86400);

        $tenant = $context->isInitialized() ? $context->tenant() : $user->tenant;
        $tenantId = $tenant?->row_id ?? $user->tenant_id;

        if ($tenantId !== null) {
            $tenantUsers = User::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->get();

            $adminRecipients = $tenantUsers->filter(
                fn (User $u): bool => $u->is_superadmin === true || $permissionChecker->allows($u, 'settings.manage')
            );

            if ($adminRecipients->isNotEmpty()) {
                Notification::send($adminRecipients, new AiAccessRequestNotification($user, $tenant));
            }
        }

        return back()->with('success', 'Permintaan langganan AI dikirim ke administrator Anda.');
    }
}

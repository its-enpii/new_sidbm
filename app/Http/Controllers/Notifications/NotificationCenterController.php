<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notifications;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanInstallment;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class NotificationCenterController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['unread_count' => 0, 'items' => []]);
        }

        $readIds = (array) ($request->session()->get('notifications_read', []));
        $items = [];
        $today = CarbonImmutable::today();

        try {
            // 1. Proposed loans needing verification / approval
            $proposedLoans = Loan::query()->where('status', 'proposed')->count();
            if ($proposedLoans > 0) {
                $id = 'loan_proposed_count';
                $items[] = [
                    'id' => $id,
                    'type' => 'loan_proposed',
                    'title' => 'Pengajuan Pinjaman Baru',
                    'message' => sprintf('Terdapat %d pengajuan pinjaman menunggu verifikasi & persetujuan.', $proposedLoans),
                    'time' => 'Membutuhkan Tindakan',
                    'target_url' => '/lending/loans',
                    'icon' => 'assignment_late',
                    'variant' => 'warning',
                    'read' => in_array($id, $readIds, true),
                ];
            }

            // 2. Overdue loan installments
            $overdueCount = LoanInstallment::query()
                ->where('status', 'pending')
                ->where('due_date', '<', $today->toDateString())
                ->count();

            if ($overdueCount > 0) {
                $id = 'loan_overdue_count';
                $items[] = [
                    'id' => $id,
                    'type' => 'loan_overdue',
                    'title' => 'Tunggakan Angsuran',
                    'message' => sprintf('Terdapat %d angsuran yang telah melewati jatuh tempo.', $overdueCount),
                    'time' => 'Perlu Perhatian',
                    'target_url' => '/lending/loans',
                    'icon' => 'warning',
                    'variant' => 'danger',
                    'read' => in_array($id, $readIds, true),
                ];
            }

            // 3. Installments due in next 7 days
            $dueSoonCount = LoanInstallment::query()
                ->where('status', 'pending')
                ->whereBetween('due_date', [$today->toDateString(), $today->addDays(7)->toDateString()])
                ->count();

            if ($dueSoonCount > 0) {
                $id = 'loan_due_soon_count';
                $items[] = [
                    'id' => $id,
                    'type' => 'installment_due',
                    'title' => 'Jatuh Tempo Mendatang',
                    'message' => sprintf('Terdapat %d angsuran jatuh tempo dalam 7 hari ke depan.', $dueSoonCount),
                    'time' => '7 Hari Ke Depan',
                    'target_url' => '/notifications/billing',
                    'icon' => 'schedule',
                    'variant' => 'info',
                    'read' => in_array($id, $readIds, true),
                ];
            }

            // 4. Default welcome/info notification if operational list is empty
            if (empty($items)) {
                $id = 'system_status_ok';
                $items[] = [
                    'id' => $id,
                    'type' => 'system',
                    'title' => 'Sistem Operasional Normal',
                    'message' => 'Tidak ada tunggakan kritikal atau pengajuan pending yang memerlukan tindakan langsung saat ini.',
                    'time' => 'Hari ini',
                    'target_url' => '/dashboard',
                    'icon' => 'check_circle',
                    'variant' => 'success',
                    'read' => in_array($id, $readIds, true),
                ];
            }
        } catch (Throwable) {
            // Silently fallback if tenant database context not available (e.g. non-tenant user)
            $id = 'system_info';
            $items[] = [
                'id' => $id,
                'type' => 'system',
                'title' => 'Sistem Informasi SIDBM',
                'message' => 'Selamat datang di Sistem Informasi Dana Bergulir Masyarakat.',
                'time' => 'Informasi',
                'target_url' => '/dashboard',
                'icon' => 'info',
                'variant' => 'info',
                'read' => in_array($id, $readIds, true),
            ];
        }

        $unreadCount = count(array_filter($items, fn ($item) => ! $item['read']));

        return response()->json([
            'unread_count' => $unreadCount,
            'items' => $items,
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $id = $request->input('id');
        $readIds = (array) ($request->session()->get('notifications_read', []));

        if (is_string($id) && $id !== '') {
            if (! in_array($id, $readIds, true)) {
                $readIds[] = $id;
            }
        } else {
            // Mark all as read
            $allIds = ['loan_proposed_count', 'loan_overdue_count', 'loan_due_soon_count', 'system_status_ok', 'system_info'];
            $readIds = array_unique(array_merge($readIds, $allIds));
        }

        $request->session()->put('notifications_read', $readIds);

        return response()->json(['success' => true]);
    }
}

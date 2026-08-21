<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notifications;

use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Lending\Models\LoanPayment;
use App\Models\User;
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

        $readIds = is_array($user->notifications_read)
            ? $user->notifications_read
            : (array) ($request->session()->get('notifications_read', []));

        $items = [];
        $today = CarbonImmutable::today();

        try {
            // 1. Proposed loans needing verification / approval
            $proposedLoans = Loan::query()->where('status', 'proposed')->count();
            if ($proposedLoans > 0) {
                $latestLoan = Loan::query()
                    ->with(['borrower.group', 'borrower.member.person'])
                    ->where('status', 'proposed')
                    ->latest('row_id')
                    ->first();

                $borrowerName = $latestLoan?->borrower?->group?->name
                    ?? $latestLoan?->borrower?->member?->person?->full_name
                    ?? 'Kelompok';

                $creatorName = null;
                if ($latestLoan?->created_by_user_id) {
                    $creator = User::query()->find($latestLoan->created_by_user_id);
                    $creatorName = $creator?->name;
                }

                $actor = $creatorName ? sprintf('%s (%s)', $creatorName, $borrowerName) : $borrowerName;
                $id = 'loan_proposed_count';

                $items[] = [
                    'id' => $id,
                    'type' => 'loan_proposed',
                    'title' => 'Pengajuan Pinjaman Baru',
                    'message' => sprintf(
                        'Terdapat %d pengajuan pinjaman menunggu verifikasi (terbaru oleh %s).',
                        $proposedLoans,
                        $actor
                    ),
                    'time' => 'Membutuhkan Tindakan',
                    'target_url' => '/lending/loans',
                    'icon' => 'assignment_late',
                    'variant' => 'warning',
                    'read' => in_array($id, $readIds, true),
                    'actor' => $actor,
                ];
            }

            // 2. Recent loan payments recorded by users
            $recentPayments = LoanPayment::query()
                ->with(['loan.borrower.group', 'loan.borrower.member.person'])
                ->latest('row_id')
                ->take(3)
                ->get();

            if ($recentPayments->isNotEmpty()) {
                $userIds = $recentPayments->pluck('created_by_user_id')->filter()->unique()->values()->all();
                $userMap = ! empty($userIds) ? User::query()->whereIn('row_id', $userIds)->pluck('name', 'row_id')->all() : [];

                foreach ($recentPayments as $payment) {
                    $borrowerName = $payment->loan?->borrower?->group?->name
                        ?? $payment->loan?->borrower?->member?->person?->full_name
                        ?? 'Peminjam';
                    $recorderName = ($payment->created_by_user_id && isset($userMap[$payment->created_by_user_id]))
                        ? $userMap[$payment->created_by_user_id]
                        : 'Kasir';

                    $id = 'payment_recent_'.$payment->row_id;
                    $items[] = [
                        'id' => $id,
                        'type' => 'payment_activity',
                        'title' => 'Penerimaan Angsuran',
                        'message' => sprintf(
                            'Pembayaran angsuran Rp %s (%s) dicatat oleh %s.',
                            number_format((float) $payment->amount, 0, ',', '.'),
                            $borrowerName,
                            $recorderName
                        ),
                        'time' => $payment->created_at?->diffForHumans() ?? 'Baru saja',
                        'target_url' => '/lending/loans',
                        'icon' => 'payments',
                        'variant' => 'success',
                        'read' => in_array($id, $readIds, true),
                        'actor' => $recorderName,
                    ];
                }
            }

            // 3. Recent journal entries created by users
            $recentJournals = JournalEntry::query()
                ->latest('row_id')
                ->take(3)
                ->get();

            if ($recentJournals->isNotEmpty()) {
                $userIds = $recentJournals->pluck('created_by_user_id')->filter()->unique()->values()->all();
                $userMap = ! empty($userIds) ? User::query()->whereIn('row_id', $userIds)->pluck('name', 'row_id')->all() : [];

                foreach ($recentJournals as $journal) {
                    $creatorName = ($journal->created_by_user_id && isset($userMap[$journal->created_by_user_id]))
                        ? $userMap[$journal->created_by_user_id]
                        : 'Petugas Akuntansi';

                    $id = 'journal_recent_'.$journal->row_id;
                    $items[] = [
                        'id' => $id,
                        'type' => 'journal_activity',
                        'title' => 'Pencatatan Jurnal Baru',
                        'message' => sprintf(
                            'Jurnal %s (%s) dicatat oleh %s.',
                            $journal->journal_number ?: 'Umum',
                            $journal->description ?: 'Transaksi Operasional',
                            $creatorName
                        ),
                        'time' => $journal->created_at?->diffForHumans() ?? 'Baru saja',
                        'target_url' => '/accounting/journals',
                        'icon' => 'receipt_long',
                        'variant' => 'info',
                        'read' => in_array($id, $readIds, true),
                        'actor' => $creatorName,
                    ];
                }
            }

            // 4. Overdue loan installments
            $overdueCount = LoanInstallment::query()
                ->where('status', 'pending')
                ->where('due_date', '<', $today->toDateString())
                ->count();

            if ($overdueCount > 0) {
                $latestOverdue = LoanInstallment::query()
                    ->with(['loan.borrower.group', 'loan.borrower.member.person'])
                    ->where('status', 'pending')
                    ->where('due_date', '<', $today->toDateString())
                    ->latest('due_date')
                    ->first();

                $overdueBorrower = $latestOverdue?->loan?->borrower?->group?->name
                    ?? $latestOverdue?->loan?->borrower?->member?->person?->full_name;

                $id = 'loan_overdue_count';
                $message = $overdueBorrower
                    ? sprintf('Terdapat %d angsuran telah melewati jatuh tempo (termasuk oleh %s).', $overdueCount, $overdueBorrower)
                    : sprintf('Terdapat %d angsuran yang telah melewati jatuh tempo.', $overdueCount);

                $items[] = [
                    'id' => $id,
                    'type' => 'loan_overdue',
                    'title' => 'Tunggakan Angsuran',
                    'message' => $message,
                    'time' => 'Perlu Perhatian',
                    'target_url' => '/lending/loans',
                    'icon' => 'warning',
                    'variant' => 'danger',
                    'read' => in_array($id, $readIds, true),
                    'actor' => $overdueBorrower ?? 'Peminjam',
                ];
            }

            // 5. Installments due in next 7 days
            $dueSoonCount = LoanInstallment::query()
                ->where('status', 'pending')
                ->whereBetween('due_date', [$today->toDateString(), $today->addDays(7)->toDateString()])
                ->count();

            if ($dueSoonCount > 0) {
                $earliestDue = LoanInstallment::query()
                    ->with(['loan.borrower.group', 'loan.borrower.member.person'])
                    ->where('status', 'pending')
                    ->whereBetween('due_date', [$today->toDateString(), $today->addDays(7)->toDateString()])
                    ->oldest('due_date')
                    ->first();

                $dueBorrower = $earliestDue?->loan?->borrower?->group?->name
                    ?? $earliestDue?->loan?->borrower?->member?->person?->full_name;

                $id = 'loan_due_soon_count';
                $message = $dueBorrower
                    ? sprintf('Terdapat %d angsuran jatuh tempo dalam 7 hari ke depan (termasuk oleh %s).', $dueSoonCount, $dueBorrower)
                    : sprintf('Terdapat %d angsuran jatuh tempo dalam 7 hari ke depan.', $dueSoonCount);

                $items[] = [
                    'id' => $id,
                    'type' => 'installment_due',
                    'title' => 'Jatuh Tempo Mendatang',
                    'message' => $message,
                    'time' => '7 Hari Ke Depan',
                    'target_url' => '/notifications/billing',
                    'icon' => 'schedule',
                    'variant' => 'info',
                    'read' => in_array($id, $readIds, true),
                    'actor' => $dueBorrower ?? 'Peminjam',
                ];
            }

            // 6. Default welcome/info notification if operational list is empty
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
                    'actor' => 'Sistem',
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
                'actor' => 'Sistem',
            ];
        }

        $unreadCount = count(array_filter($items, fn (array $item): bool => ! $item['read']));

        return response()->json([
            'unread_count' => $unreadCount,
            'items' => $items,
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $id = $request->input('id');
        $ids = $request->input('ids');

        $readIds = [];
        if ($user !== null && is_array($user->notifications_read)) {
            $readIds = $user->notifications_read;
        } else {
            $readIds = (array) ($request->session()->get('notifications_read', []));
        }

        if (is_array($ids)) {
            foreach ($ids as $singleId) {
                if (is_string($singleId) && $singleId !== '' && ! in_array($singleId, $readIds, true)) {
                    $readIds[] = $singleId;
                }
            }
        } elseif (is_string($id) && $id !== '') {
            if (! in_array($id, $readIds, true)) {
                $readIds[] = $id;
            }
        } else {
            // Mark all standard notification types as read
            $allIds = ['loan_proposed_count', 'loan_overdue_count', 'loan_due_soon_count', 'system_status_ok', 'system_info'];
            $readIds = array_unique(array_merge($readIds, $allIds));
        }

        $readIds = array_values(array_unique($readIds));

        if ($user !== null) {
            $user->notifications_read = $readIds;
            $user->save();
        }

        $request->session()->put('notifications_read', $readIds);

        return response()->json(['success' => true]);
    }
}

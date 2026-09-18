<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Mail\UpcomingNotificationMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Broadcasts upcoming-feature / system-update announcements by email to users
 * who opted into announcement email notifications.
 */
final class EmailNotificationService
{
    /**
     * Send to a single recipient.
     *
     * @return array{success: bool, recipient: ?string, message: string}
     */
    public function sendUpcoming(User $user, string $title, string $body, ?string $actionUrl = null, ?string $actionLabel = null): array
    {
        if (! $user->receivesAnnouncementEmail()) {
            return [
                'success' => false,
                'recipient' => $user->getNotificationEmail(),
                'message' => 'Pengguna tidak berlangganan email pengumuman.',
            ];
        }

        $recipient = $user->getNotificationEmail();
        if ($recipient === null || $recipient === '') {
            return [
                'success' => false,
                'recipient' => null,
                'message' => 'Pengguna tidak memiliki alamat email.',
            ];
        }

        return $this->deliver($recipient, $title, $body, $actionUrl, $actionLabel);
    }

    /**
     * Broadcast to every opted-in user of a tenant.
     *
     * @return array{success: bool, sent: int, failed: int}
     */
    public function broadcastUpcoming(int $tenantId, string $title, string $body, ?string $actionUrl = null, ?string $actionLabel = null): array
    {
        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->limit(200)
            ->get();

        $sent = 0;
        $failed = 0;
        foreach ($users as $user) {
            if (! $user->receivesAnnouncementEmail()) {
                continue;
            }
            $recipient = $user->getNotificationEmail();
            if ($recipient === null || $recipient === '') {
                continue;
            }

            $result = $this->deliver($recipient, $title, $body, $actionUrl, $actionLabel);
            $result['success'] ? $sent++ : $failed++;
        }

        return ['success' => $failed === 0, 'sent' => $sent, 'failed' => $failed];
    }

    /**
     * @return array{success: bool, recipient: ?string, message: string}
     */
    private function deliver(string $recipient, string $title, string $body, ?string $actionUrl, ?string $actionLabel): array
    {
        try {
            Mail::to($recipient)->send(new UpcomingNotificationMail(
                title: $title,
                body: $body,
                actionUrl: $actionUrl,
                actionLabel: $actionLabel ?? 'Pelajari Lebih Lanjut',
            ));

            return [
                'success' => true,
                'recipient' => $recipient,
                'message' => 'Notifikasi berhasil dikirim.',
            ];
        } catch (Throwable $e) {
            Log::error('Gagal mengirim notifikasi email', [
                'recipient' => $recipient,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'recipient' => $recipient,
                'message' => 'Gagal mengirim notifikasi email.',
            ];
        }
    }
}

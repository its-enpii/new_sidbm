<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Platform\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class AiAccessRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly User $requester,
        public readonly ?Tenant $tenant = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $tenantName = $this->tenant?->name ?? 'Usaha';
        $tenantCode = $this->tenant?->code ?? '';

        return [
            'type' => 'ai_access_request',
            'title' => 'Pengajuan Langganan AI',
            'message' => "Pengguna {$this->requester->name} mengajukan pengaktifan fitur AI untuk {$tenantName} ({$tenantCode}).",
            'requester_id' => $this->requester->row_id,
            'requester_name' => $this->requester->name,
            'tenant_code' => $tenantCode,
        ];
    }
}

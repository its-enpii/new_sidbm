<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Mail\BillingInvoiceMail;
use App\Models\Platform\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Delivers invoices by email. Recipients resolve from the tenant's billing
 * recipients (users with a notification email who opted into billing email);
 * a caller may always override with an explicit address.
 */
final class InvoiceEmailService
{
    /**
     * @return array{success: bool, recipient: ?string, message: string}
     */
    public function sendInvoice(Invoice $invoice, ?string $recipient = null): array
    {
        $invoice->loadMissing('tenant');

        $recipient = $this->resolveRecipient($invoice, $recipient);

        if ($recipient === null || $recipient === '') {
            return [
                'success' => false,
                'recipient' => null,
                'message' => 'Tidak ada penerima email yang valid untuk tagihan ini.',
            ];
        }

        try {
            Mail::to($recipient)->send(new BillingInvoiceMail($invoice));

            $meta = is_array($invoice->metadata) ? $invoice->metadata : [];
            $meta['last_email_sent_at'] = now()->toIso8601String();
            $meta['last_email_recipient'] = $recipient;
            $invoice->forceFill(['metadata' => $meta])->save();

            return [
                'success' => true,
                'recipient' => $recipient,
                'message' => "Tagihan berhasil dikirim ke {$recipient}.",
            ];
        } catch (Throwable $e) {
            Log::error('Gagal mengirim email tagihan', [
                'invoice' => $invoice->number,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'recipient' => $recipient,
                'message' => 'Gagal mengirim email tagihan. Silakan coba lagi nanti.',
            ];
        }
    }

    /**
     * @return list<string>
     */
    public function recipientsFor(Invoice $invoice): array
    {
        return $this->billingRecipients($invoice);
    }

    private function resolveRecipient(Invoice $invoice, ?string $recipient): ?string
    {
        if ($recipient !== null && $recipient !== '') {
            return trim($recipient);
        }

        $recipients = $this->billingRecipients($invoice);

        return $recipients === [] ? null : $recipients[0];
    }

    /**
     * Billing recipients for a tenant: opted-in users with a notification email,
     * ordered by tenant admin role then most recently linked Google account.
     *
     * @return list<string>
     */
    private function billingRecipients(Invoice $invoice): array
    {
        $tenantId = (int) $invoice->tenant_id;
        if ($tenantId === 0) {
            return [];
        }

        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNotNull('email')
            ->orderByRaw('CASE WHEN is_superadmin = 1 THEN 1 ELSE 2 END')
            ->orderByRaw('google_linked_at IS NULL ASC')
            ->orderBy('row_id')
            ->limit(10)
            ->get();

        $emails = [];
        foreach ($users as $user) {
            if (! $user->receivesBillingEmail()) {
                continue;
            }
            $email = $user->getNotificationEmail();
            if ($email !== null && $email !== '' && ! in_array($email, $emails, true)) {
                $emails[] = $email;
            }
        }

        return $emails;
    }
}

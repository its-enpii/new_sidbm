<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Platform\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class BillingInvoiceMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public ?string $customMessage = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tagihan SIDBM Next: {$this->invoice->number}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.billing.invoice');
    }
}

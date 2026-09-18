<?php

declare(strict_types=1);

namespace App\Models\Platform;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Invoice extends PlatformModel
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_at' => 'date',
            'paid_at' => 'datetime',
            'metadata' => 'array',
            'blocks_access' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'row_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'row_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'row_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class, 'invoice_id', 'row_id');
    }

    public const PURPOSE_LABELS = [
        'subscription' => 'Langganan Aplikasi',
        'setup' => 'Setup & Onboarding',
        'support' => 'Dukungan & Maintenance',
        'training' => 'Pelatihan',
        'custom_dev' => 'Pengembangan Custom',
        'other' => 'Lainnya',
    ];

    public const STATUS_LABELS = [
        'draft' => 'Draft',
        'issued' => 'Belum Dibayar',
        'pending_payment' => 'Menunggu Pembayaran',
        'partially_paid' => 'Dibayar Sebagian',
        'paid' => 'Lunas',
        'overdue' => 'Terlambat',
        'void' => 'Dibatalkan',
        'failed' => 'Gagal',
        'expired' => 'Kedaluwarsa',
    ];

    public function purposeLabel(): string
    {
        $purpose = (string) $this->purpose;

        return self::PURPOSE_LABELS[$purpose] ?? ($purpose !== '' ? ucfirst($purpose) : 'Lainnya');
    }

    public function statusLabel(): string
    {
        $status = (string) $this->status;

        return self::STATUS_LABELS[$status] ?? ($status !== '' ? ucfirst($status) : '-');
    }

    /** Human readable Rupiah total, e.g. "Rp 250.000". */
    public function formattedAmount(): string
    {
        $symbol = strtoupper((string) $this->currency) === 'IDR' ? 'Rp ' : strtoupper((string) $this->currency).' ';

        return $symbol.number_format((float) $this->amount, 0, ',', '.');
    }

    /** Display name used to greet the recipient in billing email templates. */
    public function recipientName(): ?string
    {
        $user = User::query()
            ->where('tenant_id', (int) $this->tenant_id)
            ->where('status', 'active')
            ->orderByRaw('google_linked_at IS NULL ASC')
            ->orderBy('row_id')
            ->first();

        return $user?->name;
    }

    public function onlineUrl(): string
    {
        return url('/billing/invoices/'.(int) $this->row_id);
    }

    public function remainingAmount(): string
    {
        return bcsub((string) $this->amount, (string) $this->amount_paid, 2);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['draft', 'issued', 'partially_paid', 'overdue', 'pending_payment'], true);
    }

    public function isBlockingAccess(): bool
    {
        return (bool) $this->blocks_access && $this->isOpen() && $this->status !== 'draft';
    }
}

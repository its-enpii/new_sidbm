@php
    /** @var \App\Models\Platform\Invoice $invoice */
    $tenant = $invoice->tenant;
    $recipientName = $invoice->recipientName();

    $statusTones = [
        'paid' => 'background-color:#97f3b5;color:#006d3d;',
        'partially_paid' => 'background-color:#ffddb5;color:#372100;',
        'overdue' => 'background-color:#ffdad6;color:#93000a;',
        'void' => 'background-color:#e6e8ea;color:#42474e;',
    ];
    $statusBadgeStyles = $statusTones[strtolower((string) $invoice->status)] ?? 'background-color:#d1e4ff;color:#002746;';
    $dueLabel = $invoice->due_at ? $invoice->due_at->translatedFormat('d F Y') : '-';
    $totalLabel = $invoice->formattedAmount();
    $invoiceUrl = $invoice->onlineUrl();
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tagihan {{ $invoice->number }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f6f8;font-family:Inter,Arial,Helvetica,sans-serif;color:#191c1e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f8;">
        <tr>
            <td align="center" style="padding:24px 16px 40px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(16,39,70,.08);">

                    {{-- Header --}}
                    <tr>
                        <td style="background-color:#002746;padding:28px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="color:#ffffff;font-size:20px;font-weight:800;letter-spacing:-.02em;">SIDBM Next</td>
                                    <td align="right" style="color:#d1e4ff;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;">Tagihan</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 8px;font-size:15px;font-weight:700;color:#002746;">Halo{{ $recipientName ? ', ' . $recipientName : '' }},</p>
                            <p style="margin:0 0 24px;font-size:14px;line-height:1.65;color:#42474e;">
                                @if (! empty($customMessage))
                                    {{ $customMessage }}
                                @else
                                    Berikut rincian tagihan langganan Anda. Mohon selesaikan pembayaran sebelum tanggal jatuh tempo agar layanan tetap aktif.
                                @endif
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #c2c7cf;border-radius:12px;overflow:hidden;margin-bottom:24px;">
                                <tr>
                                    <td style="padding:14px 16px;font-size:12px;font-weight:700;color:#42474e;background-color:#f2f4f6;width:42%;">Nomor Tagihan</td>
                                    <td style="padding:14px 16px;font-size:14px;font-weight:700;color:#002746;">{{ $invoice->number }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;font-size:12px;font-weight:700;color:#42474e;background-color:#f2f4f6;border-top:1px solid #c2c7cf;">Untuk Kepentingan</td>
                                    <td style="padding:14px 16px;font-size:14px;font-weight:600;color:#002746;border-top:1px solid #c2c7cf;">{{ $invoice->purposeLabel() }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;font-size:12px;font-weight:700;color:#42474e;background-color:#f2f4f6;border-top:1px solid #c2c7cf;">Tanggal Jatuh Tempo</td>
                                    <td style="padding:14px 16px;font-size:14px;font-weight:600;color:#002746;border-top:1px solid #c2c7cf;">{{ $dueLabel }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;font-size:12px;font-weight:700;color:#42474e;background-color:#f2f4f6;border-top:1px solid #c2c7cf;">Status</td>
                                    <td style="padding:14px 16px;border-top:1px solid #c2c7cf;">
                                        <span style="display:inline-block;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;{{ $statusBadgeStyles }}">{{ $invoice->statusLabel() }}</span>
                                    </td>
                                </tr>
                                @if (! empty($invoice->description))
                                    <tr>
                                        <td style="padding:14px 16px;font-size:12px;font-weight:700;color:#42474e;background-color:#f2f4f6;border-top:1px solid #c2c7cf;">Deskripsi</td>
                                        <td style="padding:14px 16px;font-size:14px;line-height:1.55;color:#002746;border-top:1px solid #c2c7cf;">{{ $invoice->description }}</td>
                                    </tr>
                                @endif
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#002746;border-radius:12px;margin-bottom:24px;">
                                <tr>
                                    <td style="padding:18px 20px;font-size:12px;font-weight:700;color:#a2cafa;text-transform:uppercase;letter-spacing:.08em;">Total Tagihan</td>
                                    <td align="right" style="padding:18px 20px;font-size:22px;font-weight:800;color:#ffffff;">{{ $totalLabel }}</td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $invoiceUrl }}" style="display:inline-block;padding:14px 28px;background-color:#006d3d;border-radius:10px;color:#ffffff;font-size:15px;font-weight:800;text-decoration:none;">Lihat &amp; Bayar Tagihan</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 0;font-size:12px;line-height:1.6;color:#73777f;">
                                Tagihan ini diterbitkan{{ $invoice->issued_at ? ' pada ' . $invoice->issued_at->translatedFormat('d F Y') : '' }}{{ $tenant ? ' untuk ' . $tenant->name : '' }}.
                                Jika Anda merasa tidak pernah melakukan transaksi ini, abaikan email ini atau hubungi administrator.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 32px;border-top:1px solid #c2c7cf;background-color:#f2f4f6;">
                            <p style="margin:0;font-size:12px;line-height:1.6;color:#73777f;">
                                Email ini dikirim otomatis oleh SIDBM Next. Mohon tidak membalas email ini.<br>
                                &copy; {{ date('Y') }} SIDBM Next. Seluruh hak cipta dilindungi.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

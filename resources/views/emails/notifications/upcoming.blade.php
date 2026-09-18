@php
    $actionUrl = $actionUrl ?? null;
    $actionLabel = $actionLabel ?? 'Pelajari Lebih Lanjut';
    $recipientName = $recipientName ?? null;
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
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
                                    <td align="right" style="color:#d1e4ff;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;">Pembaruan Fitur</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 8px;font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#006d3d;">Segera Hadir</p>
                            <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;font-weight:800;color:#002746;">{{ $title }}</h1>
                            <p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#42474e;white-space:pre-line;">{{ $body }}</p>

                            @if (! empty($actionUrl))
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td align="center">
                                            <a href="{{ $actionUrl }}" style="display:inline-block;padding:14px 28px;background-color:#002746;border-radius:10px;color:#ffffff;font-size:15px;font-weight:800;text-decoration:none;">{{ $actionLabel }}</a>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            <p style="margin:24px 0 0;font-size:12px;line-height:1.6;color:#73777f;">
                                Anda menerima email ini karena berlangganan pengumuman pembaruan fitur SIDBM Next.
                                Pengaturan notifikasi dapat diubah kapan saja di halaman Profil.
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

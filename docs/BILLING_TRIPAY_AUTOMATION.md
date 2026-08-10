# Spesifikasi Integrasi Tripay, Auto-Invoice & Auto-Deactivation Tenant

Document ID: `docs/BILLING_TRIPAY_AUTOMATION.md`  
Tanggal Keputusan: 2026-08-09  
Status: Active / In Development

---

## 1. Keputusan Prioritas

Berdasarkan evaluasi dampak bisnis, kesiapan kode (*codebase readiness* ~75%), dan kebutuhan SaaS multi-tenant:

1. **Prioritas 1 (Aktif)**: Integrasi Tripay (Scan QRIS / VA langsung dari aplikasi), otomatisasi generate invoice langganan aplikasi, dan automatisasi penonaktifan tenant saat melewati tenggat bayar langganan (*overdue*).
2. **Prioritas 2**: Implementasi role/user level Kabupaten (Supervisory read-only dashboard & laporan keuangan konsolidasi per kecamatan & gabungan kabupaten).
3. **Prioritas 3**: Melengkapi varian laporan legacy penunjang yang tersisa.

---

## 2. Arsitektur Billing & Tripay

### 2.1 Alur Pembayaran Tripay
- Tenant / Admin mengakses menu Billing (`/billing/invoices` atau `/admin/invoices/{invoice}`).
- Klik **Bayar via Tripay** / **Generate QRIS**.
- Application memanggil `InvoicePaymentService::initiateTripay($invoice)`.
- System membuat record `InvoicePayment` dengan `method='tripay'`, `status='pending'`, dan meminta transaction payload ke Tripay API (`TripayClient`).
- Mengembalikan checkout URL / QR code ke frontend Vue untuk ditampilkan ke user.
- Webhook Callback Tripay diterima di endpoint `POST /api/billing/tripay/callback` (tanpa middleware auth CSRF, diverifikasi menggunakan Tripay Signature HMAC-SHA256).
- `InvoicePaymentService::handleTripayCallback()` memproses update status:
  - `PAID` -> mengupdate status `InvoicePayment` menjadi `paid`, mengupdate `amount_paid` pada `Invoice`, merefresh status `Invoice` (`paid` / `partially_paid`), dan memperbarui tanggal aktif `Subscription`.

---

## 3. Otomatisasi Generate Invoice Langganan

- **Command Artisan**: `php artisan subscriptions:generate-invoices`
- **Jadwal**: Dijalankan harian via Laravel Scheduler (`routes/console.php` / `app/Console/Kernel.php`).
- **Logika**:
  - Mencari seluruh `Subscription` dengan status `active` yang periode tagihannya akan jatuh dalam $N$ hari (misal 7 hari sebelum `next_billing_at` atau tepat pada hari H).
  - Memeriksa apakah invoice periode tersebut sudah pernah dibuat (mencegah duplikasi).
  - Memanggil `InvoiceService::generateFromSubscription($subscription)`.
  - Mengirim notifikasi invoice baru (opsional/log).

---

## 4. Automatisasi Penonaktifan Tenant Overdue

### 4.1 Definisi Tenant Overdue & Inactive
- Invoice berstatus `overdue` apabila `due_at < NOW()` dan `amount_paid < amount`.
- Jika tenant memiliki invoice `overdue` yang melewati *grace period* (misal: 3 hari setelah `due_at`), maka `Subscription` atau status `Tenant` dinyatakan `suspended` / `overdue_restricted`.

### 4.2 Restriksi Akses (Middleware)
- **Middleware**: `EnsureTenantActive` / `EnsureSubscriptionActive`.
- **Aturan**:
  - Mengizinkan akses ke route Billing (`/billing/invoices`, `/billing/invoices/*`, `/login`, `/logout`).
  - Memblokir akses ke modul operasional (`/accounting/*`, `/lending/*`, `/master-data/*`) dan menampilkan halaman peringatan *"Langganan Anda telah melewati batas waktu pembayaran. Silakan lakukan pembayaran tagihan untuk melanjutkan penggunaan aplikasi."*

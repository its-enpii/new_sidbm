# Spesifikasi & Dokumentasi Integrasi Payment Gateway (Duitku & Tripay), Auto-Invoice & Auto-Deactivation Tenant

Document ID: docs/BILLING_TRIPAY_AUTOMATION.md  
Tanggal Pembaruan: 2026-08-10  
Status: **COMPLETED / IN PRODUCTION**

---

## 1. Status Implementasi

Seluruh item pada arsitektur billing, pembayaran otomatis, dan pembatasan tenant overdue telah **selesai diimplementasikan dan diuji secara menyeluruh**:

- ✅ **Multi Payment Gateway Integration (Duitku & Tripay)**: Mendukung Duitku Payment Gateway (DuitkuClient) dan Tripay Payment Gateway (TripayClient) dengan konfigurasi kredensial terpusat dari Superadmin (fallback ke .env) dan tombol switch Gateway Utama di Admin Integrasi.
- ✅ **Saluran Pembayaran Lengkap**: QRIS (display QR langsung in-app), E-Wallet (ShopeePay/GoPay/OVO/Dana), Bank Virtual Accounts (BCA, BRI, BNI, Mandiri, Permata, CIMB, BSI, Danamon), serta Kartu Kredit (Duitku).
- ✅ **In-App Payment Interface (esources/js/Pages/Billing/Invoices/Show.vue)**: Pilihan channel interaktif, instruksi transfer, tombol copy nominal/kode bayar, dan indikator countdown waktu kadaluarsa.
- ✅ **Automated Subscription Invoices (subscriptions:generate-invoices)**: Scheduler harian untuk membuat tagihan otomatis menjelang masa perpanjangan langganan.
- ✅ **Overdue Grace Period & Enforcement (subscriptions:check-overdue & EnsureSubscriptionActive)**: Deteksi tagihan menunggak setelah grace-period (3 hari), penangguhan otomatis langganan (suspended), dan pembatasan akses modul operasional secara otomatis via middleware.
- ✅ **Real-Time Webhook & Status Sync (InvoicePaymentService)**: Pemrosesan callback HMAC-SHA256 dari Tripay, perpanjangan otomatis masa aktif Subscription, dan tombol manual "Cek Status Pembayaran".

---

## 2. Arsitektur Billing & Tripay

### 2.1 Alur Pembayaran Tripay
1. Tenant / Admin membuka detail tagihan (/billing/invoices/{invoice}).
2. Memilih channel pembayaran (QRIS atau Virtual Account Bank tertentu) dan mengklik **Dapatkan Kode Pembayaran**.
3. Controller memanggil InvoicePaymentService::initiateTripay(, , ).
4. Sistem membuat record InvoicePayment (method='tripay', status='pending') dan meminta payload transaksi dari Tripay API (TripayClient::createTransaction()).
5. Frontend Vue menampilkan QR code / Nomor Virtual Account beserta petunjuk pembayaran secara langsung (tanpa perlu redirect keluar aplikasi).
6. Saat pembayaran selesai, Tripay memanggil webhook POST /api/billing/tripay/callback (diverifikasi dengan Signature HMAC-SHA256).
7. Webhook Handler (`DuitkuWebhookController` atau `TripayWebhookController`) memverifikasi Signature (MD5 / HMAC-SHA256) dan memanggil `InvoicePaymentService::handleDuitkuCallback()` / `handleTripayCallback()` untuk memproses update:
   - PAID -> mengupdate status InvoicePayment menjadi paid, menambah mount_paid pada Invoice, merefresh status Invoice (paid), dan memperbarui tanggal aktif Subscription via SubscriptionService::renewFromPaidInvoice().

---

## 3. Command Scheduler & Automation

- php artisan subscriptions:generate-invoices --days=7
  - Dijalankan harian pada pukul 01:00.
  - Memeriksa langganan ctive yang mendekati jatuh tempo dan belum memiliki invoice terbuka.
- php artisan subscriptions:check-overdue --grace-days=3
  - Dijalankan harian pada pukul 01:30.
  - Mengubah status tagihan menjadi overdue dan menangguhkan langganan tenant (status = suspended).

---

## 4. Pengujian (Testing)

Semua skenario billing dan otomatisasi diuji dalam test suite:
- Tests\Feature\Billing\InAppPaymentChannelTest (3 tests — channel display, VA initiation, status sync)
- Tests\Feature\Billing\SubscriptionAutomationAndEnforcementTest (4 tests — invoice generation, overdue suspension, route blocking middleware, subscription renewal)
- Tests\Feature\Billing\TenantInvoicePortalTest (4 tests — multi-tenant isolation pada invoice portal)

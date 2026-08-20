# Changelog

Semua perubahan penting pada proyek **SIDBM Next** didokumentasikan dalam berkas ini.
Format penulisan mengikuti panduan [Keep a Changelog](https://keepachangelog.com/id/1.0.0/).

---

## [Unreleased]

### Added
- **Export Laporan Keuangan Format Excel Native:**
  - Implementasi engine writer OpenXML/ZIP standalone tanpa dependensi luar (`App\Support\Excel\XlsxWriter` & `App\Support\Excel\ReportExcel`) untuk export 8 laporan akuntansi (Neraca, Laba Rugi, Arus Kas, Perubahan Ekuitas, CALK, Buku Besar, Neraca Saldo, dan Jurnal).
  - Format angka nominal langsung diformat mata uang Rupiah (`#,##0`).
  - Penambahan endpoint download Excel pada `ReportController.php` dan tombol aksi download pada `ReportPeriodFilter.vue`.
  - Penambahan unit/feature test di `tests/Feature/Accounting/Reports/ExcelExportTest.php`.
- **Opsi Pembulatan Angsuran Tambahan:**
  - Penambahan opsi pembulatan angsuran pinjaman ke Rp 500, Rp 1.000, Rp 5.000, Rp 10.000, dan Rp 50.000 pada pengaturan sistem (`resources/js/Pages/Settings/Index.vue`) dan kalkulasi jadwal angsuran pinjaman.
- **Pengujian E2E Live Chat Assistant:**
  - Penambahan skenario Playwright test `tests/e2e/live_chat_test.spec.ts` untuk memverifikasi streaming respon Server-Sent Events (SSE) AI assistant secara end-to-end.

### Changed
- **Pembaruan Package `enpii/assistant`:**
  - Pembaruan dependensi `enpii/assistant` ke commit terbaru (`2c676f0`) pada `composer.json` & `composer.lock`.
  - Integrasi controller `App\Http\Controllers\Admin\AiAssistantController::chatStream` dan rute `/assistant/*` agar sesuai dengan signature `AgentLoop::run(...)` dan `SseEmitter` terbaru dari package.
  - Penyesuaian urutan navigasi sidebar RBAC pada `resources/js/Layouts/AuthenticatedLayout.vue` ("Manajemen Role" sebelum "Manajemen User").
  - Form entri jurnal umum (`resources/js/Pages/Accounting/JournalEntries/Create.vue`) disesuaikan menjadi lebar penuh (*full-width*) dan perataan tombol "Cek riwayat/saldo".

### Fixed
- **Penyelarasan Kolom Percakapan AI (`ai_conversations`):**
  - Perbaikan query create conversation pada `AiAssistantController` dari `user_id` menjadi `external_user_id` untuk mengatasi error SQLSTATE[23502] Not Null Violation pada database Postgres/RAG.
- **Migrasi Database RAG & Default:**
  - Eksekusi migrasi `2026_08_20_000001_add_message_attachments` untuk menambahkan kolom `attachments_json` pada tabel `ai_messages` di koneksi default dan `rag` (PostgreSQL).
- **Resolusi Domain Tenant:**
  - Optimalisasi pencocokan domain tenant pada `app/Tenancy/TenantResolver.php` dengan query yang database-agnostic.

### Removed
- **Pembersihan Folder Legacy `packages/assistant`:**
  - Menghapus folder lama `packages/assistant/` dan direktori `packages/` karena package kini telah dikelola secara mandiri via Composer (`vendor/enpii/assistant`).
  - Memperbarui seluruh referensi path migrasi dan rute di `app/Services/TenantRegistrationService.php`, `routes/web.php`, dan `.github/workflows/deploy.yml` ke `vendor/enpii/assistant/`.

---
## [2026-08-18]

### Added
- **Fitur Koreksi Jurnal Akuntansi (Journal Edit & Reverse-and-Replace):**
  - Halaman edit jurnal akuntansi (`Accounting/JournalEntries/Edit.vue`) dengan riwayat alasan koreksi.
  - Service `JournalEditService` untuk pembatalan jurnal lama (reverse) dan pembuatan entri jurnal revisi secara atomik.
- **Fitur Saldo Awal Manual & Jurnal Agregat Mid-Year:**
  - Modul input saldo awal manual per tahun fiskal pada Import Wizard.
  - Form posting jurnal agregat mid-year untuk migrasi pembukuan berjalan.
- **Fitur Pembatalan Reschedule Pinjaman:**
  - Endpoint & request validation `LoanRescheduleCancelRequest` untuk membatalkan status restrukturisasi pinjaman yang belum diproses.
- **Manajemen Revenue Platform Admin:**
  - Halaman dan controller `RevenueController` (`Admin/Revenue/Index.vue`) untuk monitoring omzet langganan platform per tenant.

### Changed
- **Pembersihan Hardcoded Color ke Token MD3:**
  - Penggantian total kelas warna hardcoded Tailwind (`gray-*`, `slate-*`, `emerald-*`, `amber-*`, `red-*`, `rose-*`, `blue-*`) ke token semantik Material Design 3 (`surface-*`, `primary-*`, `secondary-*`, `tertiary-*`, `error-*`).

---

## [2026-08-15]

### Added
- **Penghapusan Pemanfaat Pinjaman (Write-Off Individual):**
  - Dukungan penghapusan pemanfaat macet tingkat anggota individu dalam kelompok pinjaman SPP/UEP tanpa menghapus seluruh kelompok.
- **Dukungan Desimal & Format Koma Rupiah:**
  - Komponen `AppCurrencyInput` mendukung input angka desimal bertanda koma sesuai format standar Indonesia.

### Fixed
- **AI Assistant Tooling & Payload:**
  - Perbaikan pemanggilan skema tool handler `jsonSchema()` pada sinkronisasi tool AI Assistant.
  - Penyesuaian fallback conversation dan detail dokumen regulasi di `AiAssistantController`.
- **Kalkulasi Portofolio & Pinjaman Aktif:**
  - Sinkronisasi perhitungan jumlah pinjaman aktif dan sisa pokok piutang pada dashboard tenant.

---

## [2026-08-14]

### Added
- **Sistem Notifikasi WhatsApp Gateway:**
  - Pengiriman pesan otomatis untuk jadwal angsuran, konfirmasi pembayaran, dan tagihan invoice.
- **Penyempurnaan Modul Onboarding & Migrasi Shard:**
  - Runner cutover data eksisting (SIDBM Access / Excel) dengan validasi akun debit-kredit otomatis.
  - Peningkatan idempotensi loader master data anggota dan kelompok.
- **Komponen UI Baru:**
  - `AppFilterPill.vue` untuk filter status interaktif.
  - `AppTabs.vue` untuk navigasi tab modular.

---

## [2026-07-26]

### Added
- **Konfigurasi Pembulatan Angsuran (Rounding Methods):**
  - Opsi pembulatan pinjaman: `decimal_2`, `rupiah_bersih`, `ceil_100`, `floor_100`, serta nominal kelipatan ratusan hingga puluhan ribu.
- **Isolasi Sharding Multi-Tenant:**
  - Arsitektur basis data terpisah per tenant BUMDesma dengan koneksi dinamis.

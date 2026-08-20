# Changelog

Semua perubahan penting pada proyek **SIDBM Next** didokumentasikan dalam berkas ini.
Format penulisan mengikuti panduan [Keep a Changelog](https://keepachangelog.com/id/1.0.0/).

---

## [2026-08-20]

### Fixed
- **Optimasi Key Transisi Halaman (Mencegah Kedipan / Blink pada Modal & Filter Query):**
  - Mengubah binding `:key` pada wrapper `<Transition name="page">` di seluruh layout dari `$page.url` menjadi `currentPath` (path tanpa query string).
  - Mencegah unmount/re-render seluruh halaman saat membuka/menutup modal pipeline dashboard (`?pipeline=...`) atau saat memfilter data dengan parameter URL sehingga modal terbuka/tertutup instan dan mulus tanpa kedipan layar putih.

### Added
- **Animasi Transisi Halaman Mulus (Smooth Page Transitions):**
  - Implementasi komponen `<Transition name="page" mode="out-in" appear>` pada 4 layout utama (`AuthenticatedLayout.vue`, `AdminLayout.vue`, `ProvinceLayout.vue`, `RegencyLayout.vue`) berbasis `:key="$page.url"`.
  - Styling transisi kurva cubic-bezier (`resources/css/app.css`) dengan efek *fade*, *subtle lift* (8px), dan *scale easing* (0.22s) yang cepat dan responsif tanpa lag.
  - Efek visual *glowing accent line* dengan *drop-shadow* lembut pada bilah progres Inertia (NProgress) saat navigasi data berlangsung.
  - Kepatuhan aksesibilitas penuh terhadap pengaturan sistem operasi (`@media (prefers-reduced-motion: reduce)`).
- **Penyempurnaan Animasi Interaktif Beranda & Portal Login (`Home.vue` & `Login.vue`):**
  - **Beranda (`Home.vue`)**: Interaksi 3D tilt parallax pada kartu mockup hero dengan respon pergerakan kursor mouse, floating pills multi-layer, animasi ambient glowing orbs berkala, dan micro-interaction spring pada kartu fitur.
  - **Portal Login (`Login.vue`)**: Interaksi 3D parallax pada panel informasi kiri, animasi live breathing bar chart keuangan, micro-interaction scale bounce pada toggle sandi, dan spring shake form saat validasi gagal.
- **Dokumentasi Lengkap Panduan Pengguna (User Manual) (docs/USER_GUIDE.md):**
  - Penyusunan dokumen panduan operasional komprehensif (35,7 KB, 452 baris) dalam Bahasa Indonesia mencakup seluruh 86 halaman dan alur kerja aplikasi SIDBM Next.
  - Dokumentasi lengkap untuk 24 bab: Mulai dari Autentikasi, Dashboard Drilldown, Master Data, Siklus Perguliran Pinjaman (6 tahapan), Akuntansi Double-Entry & Immutable Ledger, Inventaris Aset, E-Budgeting, 16 Laporan Keuangan & Piutang, Billing SaaS Multi-Gateway, Notifikasi WhatsApp, RBAC 37 permissions, Wizard Onboarding, Portal Supervisi Kabupaten/Provinsi, Superadmin SaaS, AI Assistant (Ariel), hingga Katalog 36 Dokumen Cetak PDF.
- **Restrukturisasi Indeks Dokumentasi (docs/README.md & README.md):**
  - Pengelompokan seluruh 15 dokumen teknis ke dalam 4 kategori terstruktur: Panduan Pengguna & Operasional, Arsitektur & Spesifikasi Sistem, Analisis Komparatif & Migrasi Legacy, serta Roadmap & Riwayat Pengujian.
- **Fitur Impersonasi Tenant Superadmin & Holding Sync API:**
  - Implementasi login impersonasi satu klik dari panel Superadmin ke akun tenant/pengguna dengan token temporer berbatas waktu (TenantImpersonationService).
  - Penambahan banner peringatan impersonasi aktif di \AuthenticatedLayout.vue\ dengan tombol kembali ke akun superadmin.
  - Pembuatan endpoint API sinkronisasi tenant holding (HoldingTenantSyncController) dan panduan integrasi docs/HOLDING_API_INTEGRATION_GUIDE.md.
  - Penambahan tabel migrasi \	enant_impersonation_tokens dan test suite TenantImpersonationTest.php.
- **API Laporan Keuangan untuk Integrasi Aplikasi Holding (`routes/api.php`):**
  - Implementasi rute RESTful API lengkap untuk integrasi aplikasi Holding / BUMDesma Induk yang mencakup 5 laporan keuangan utama:
    - **Neraca / Balance Sheet** (`/api/v1/holding/reports/balance-sheet` & `/api/v1/holding/tenants/{tenant}/reports/balance-sheet`).
    - **Laba Rugi / Income Statement** (`/api/v1/holding/reports/income-statement` & `/api/v1/holding/tenants/{tenant}/reports/income-statement`).
    - **Arus Kas / Cash Flow** (`/api/v1/holding/reports/cash-flow` & `/api/v1/holding/tenants/{tenant}/reports/cash-flow`).
    - **Catatan Atas Laporan Keuangan / CALK** (`/api/v1/holding/reports/calk` & `/api/v1/holding/tenants/{tenant}/reports/calk`).
    - **Perubahan Ekuitas / Modal** (`/api/v1/holding/reports/equity-changes` & `/api/v1/holding/tenants/{tenant}/reports/equity-changes`).
    - **Paket Lengkap / Financial Report Pack** (`/api/v1/holding/reports/pack` & `/api/v1/holding/tenants/{tenant}/reports/pack`) � mengembalikan 5 laporan keuangan sekaligus dalam 1 request roundtrip.
    - **Laporan Keuangan Konsolidasi** (`/api/v1/holding/reports/consolidated/*`) � laporan konsolidasi seluruh anak perusahaan / unit usaha holding.
    - **Direktori Anak Usaha / Tenants Discovery** (`/api/v1/holding/tenants` & `/api/v1/holding/tenants/{tenant}`).
  - Controller `App\Http\Controllers\Api\Holding\HoldingReportController` dan `App\Http\Controllers\Api\Holding\HoldingTenantController`.
  - Middleware otentikasi API Key `App\Http\Middleware\VerifyHoldingApiToken` (`holding.auth`) dengan dukungan Bearer token, header `X-Holding-Key`, `X-API-Key`, query parameter `api_key`, dan platform supervisor bypass.
  - Automated feature test suite `tests/Feature/Api/HoldingReportApiTest.php` dengan 12 test cases yang lolos 100%.
- **Animasi Interaktif GSAP pada Landing Page & Login Portal:**
  - Integrasi library `gsap` pada `package.json` untuk micro-interaction dan transisi visual modern berstandar Material Design 3.
  - **Landing Page (`resources/js/Pages/Home.vue`)**:
    - Staggered timeline entrance untuk navbar, hero badge, headline gradient, deskripsi, tombol CTA, dan trust badges.
    - Continuous floating levitation & ambient glow blob pada kartu mockup portofolio dan floating feature badges.
    - Animated number counter yang menghitung naik secara dinamis saat section statistik masuk ke viewport (`IntersectionObserver`).
    - Staggered scroll-reveal animation untuk grid kartu fitur utama, alur kerja tahapan sistem, dan FAQ accordion.
  - **Login Portal (`resources/js/Pages/Auth/Login.vue`)**:
    - Ambient glowing floating circles dan animasi pertumbuhan grafik batang (*bar chart growth with back easing*) pada panel branding kiri.
    - Staggered entrance untuk header, input fields, checkbox, tombol submit, dan info bantuan pada panel form login.
    - Animasi horizontal shake interaktif pada container form ketika validasi submit gagal.
- **Export Laporan Keuangan Format Excel Native:**
  - Implementasi engine writer OpenXML/ZIP standalone tanpa dependensi luar (`App\Support\Excel\XlsxWriter` & `App\Support\Excel\ReportExcel`) untuk export 8 laporan akuntansi (Neraca, Laba Rugi, Arus Kas, Perubahan Ekuitas, CALK, Buku Besar, Neraca Saldo, dan Jurnal).
  - Format angka nominal otomatis dengan format mata uang Rupiah (`#,##0`).
  - Penambahan endpoint download Excel pada `ReportController.php` dan tombol aksi download pada `ReportPeriodFilter.vue`.
  - Penambahan unit/feature test di `tests/Feature/Accounting/Reports/ExcelExportTest.php`.
- **Opsi Pembulatan Angsuran Tambahan:**
  - Penambahan opsi pembulatan angsuran pinjaman ke Rp 500, Rp 1.000, Rp 5.000, Rp 10.000, dan Rp 50.000 pada pengaturan sistem (`resources/js/Pages/Settings/Index.vue`) dan kalkulasi jadwal angsuran pinjaman.
- **Pengujian E2E Live Chat Assistant:**
  - Penambahan skenario Playwright test `tests/e2e/live_chat_test.spec.ts` untuk memverifikasi streaming respon Server-Sent Events (SSE) AI assistant secara end-to-end.
- **Autentikasi CI/CD Composer untuk Private Package:**
  - Penambahan environment variable `COMPOSER_AUTH` pada workflow deployment (`.github/workflows/deploy.yml`) untuk otorisasi download private package `enpii/assistant`.

### Changed
- **Pembaruan Package `enpii/assistant`:**
  - Pembaruan dependensi `enpii/assistant` ke commit terbaru (`2c676f0`) pada `composer.json` & `composer.lock`.
  - Penyesuaian `App\Http\Controllers\Admin\AiAssistantController::chatStream` dan rute `/assistant/*` agar sesuai dengan signature `AgentLoop::run(...)` dan `SseEmitter` terbaru dari package.
  - Penyesuaian urutan navigasi sidebar RBAC pada `resources/js/Layouts/AuthenticatedLayout.vue` ("Manajemen Role" sebelum "Manajemen User").
  - Form entri jurnal umum (`resources/js/Pages/Accounting/JournalEntries/Create.vue`) disesuaikan menjadi lebar penuh (*full-width*) dan penyesuaian tombol "Cek riwayat/saldo".

### Fixed
- **Penyelarasan Kolom Percakapan AI (`ai_conversations`):**
  - Perbaikan pembuatan conversation pada `AiAssistantController` menggunakan `external_user_id` untuk mengatasi error SQLSTATE[23502] Not Null Violation pada database Postgres/RAG.
- **Migrasi Database RAG & Default:**
  - Eksekusi migrasi `2026_08_20_000001_add_message_attachments` untuk penambahan kolom `attachments_json` pada tabel `ai_messages` di database default dan `rag` (PostgreSQL).
- **Resolusi Domain Tenant:**
  - Optimalisasi pencocokan domain tenant pada `app/Tenancy/TenantResolver.php` dengan query yang database-agnostic.

### Removed
- **Pembersihan Folder Legacy `packages/assistant`:**
  - Menghapus folder `packages/assistant/` dan direktori `packages/` karena package telah dikelola via Composer (`vendor/enpii/assistant`).
  - Memperbarui seluruh referensi path migrasi dan rute di `app/Services/TenantRegistrationService.php`, `routes/web.php`, dan `.github/workflows/deploy.yml` ke `vendor/enpii/assistant/`.

---

## [2026-08-19]

### Added
- **Redesain Dashboard Admin & Monitor Pendapatan Tenant:**
  - Pemindahan chart tren pendapatan/penagihan tahunan ke halaman utama **Dashboard Admin Platform** (`resources/js/Pages/Admin/Dashboard.vue` & `app/Http/Controllers/Admin/DashboardController.php`) dengan metrik KPI bisnis komprehensif (Pendapatan Bulan Ini, Pertumbuhan MoM, Pendapatan YTD, Total Piutang Belum Bayar, dan Tagihan Terbuka).
  - Redesain halaman **Pendapatan** (`resources/js/Pages/Admin/Revenue/Index.vue` & `app/Http/Controllers/Admin/RevenueController.php`) menjadi **Billing Overview** dengan analitik invoice, perbandingan kuartal, dan tabel rincian transaksi berpaginasi.
- **Global Search Keyboard Shortcut Modal:**
  - Komponen `GlobalSearchModal.vue` dengan pintasan keyboard `Ctrl+K` / `Cmd+K` untuk navigasi cepat antar menu, aksi, data anggota, kelompok, dan invoice.
- **Tool AI Assistant WhatsApp Gateway:**
  - Tool baru `send_whatsapp_message` dan `check_whatsapp_status` pada AI Assistant untuk kirim pesan/notifikasi langsung via obrolan AI.

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

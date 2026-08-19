# Changelog

Semua perubahan penting pada proyek **SIDBM Next** didokumentasikan dalam berkas ini.
Format penulisan mengikuti panduan [Keep a Changelog](https://keepachangelog.com/id/1.0.0/).

---

## [Unreleased]

### Added
- **Penanganan Koneksi Terputus / Offline PWA:**
  - Komponen AppOfflineBanner (`resources/js/Components/AppOfflineBanner.vue`) berbasis token MD3 dengan floating notification, tombol cek koneksi, dan status reconnect pulih.
  - Handler event exception di Inertia router (`resources/js/app.js`) untuk pencegahan error page browser saat navigasi tanpa koneksi.
  - Penyempurnaan Service Worker PWA (`public/sw.js`) dan template offline fallback (`public/offline.html`) dengan auto-reload polling.
- **Manajemen Hak Akses & Peran Pengguna (Access Control / RBAC):**
  - Modul manajemen Role & Permission dinamis (`app/Domain/Access/Models/Role.php`, `RoleController`, `app/Http/Requests/Access/`).
  - Halaman antarmuka pengelolaan peran dan matriks izin (`resources/js/Pages/Access/Roles/`).
  - Migrasi shard `2026_08_18_000004_add_permissions_to_roles.php` untuk menyimpan permission granular per role.
  - Integrasi pengecekan izin terpusat via `PermissionChecker` dan composable `useCan`.
- **Fitur Tenant Data Purifier & Training Mode:**
  - Platform service `TenantDataPurifierService` & controller `TenantDataPurifierController` untuk pembersihan data uji/dummy pada database shard tenant.
  - Antarmuka visual purifier data di `resources/js/Pages/Admin/Tenants/DataPurifier/Index.vue`.
  - Migrasi platform `2026_08_18_100000_add_training_mode_to_tenants.php` untuk mendukung mode pelatihan (*training mode*) per tenant.
- **Standarisasi Encoding & UTF-8 Otomatis:**
  - Penambahan berkas konfigurasi `.editorconfig` di root project untuk penegakan otomatis encoding UTF-8 dan format line-ending `LF` di seluruh IDE/editor.
  - Pembuatan skrip profil PowerShell `$PROFILE` untuk default parameter encoding UTF-8 secara sistemik.
  - Pembaruan protokol kerja di `AGENT.md` yang mewajibkan audit komprehensif dan update `CHANGELOG.md` sebelum push.
- **UI Audit & Atomic Component Standardization:**
  - Konversi seluruh tombol hardcoded di halaman dan layout ke `AppButton` & `AppIconButton`.
  - Integrasi `AppDatePicker` dan `AppInput` pada form *Import Wizard* (`Onboarding/ImportWizard.vue`).
  - Integrasi `AppTextarea` pada form koreksi jurnal (`Accounting/JournalEntries/Edit.vue`).
- **Responsive Layout & Breakpoint Scaling:**
  - Breakpoint baru `--breakpoint-3xl: 120rem` (1920px) di Tailwind `@theme` untuk monitor widescreen desktop 24"+.
  - Adaptive root typography scaling pada `resources/css/app.css` (14px pada layar laptop < 1600px, 16px pada monitor 24"+).
- **SmartSelect Text Truncation:**
  - Penambahan class `block min-w-0 flex-1 truncate whitespace-nowrap` pada trigger button `SmartSelect.vue` agar opsi teks panjang terpotong rapi dengan elipsis (`...`).

### Fixed
- **Pembersihan Karakter Mojibake (Encoding Glitch):**
  - Pembersihan total karakter rusak (`—`, `·`, `→`, `� �`, `…`) pada `AGENT.md`, `Admin/AiAssistant/Index.vue`, dan `Accounting/PeriodClose/Index.vue` kembali ke karakter murni (`�`, `�`, `?`, `?`, `�`, `?`, `??`).
- **Shard Database Migrations:**
  - Perbaikan query migrasi constraint `chk_loan_products_rounding` di MySQL/MariaDB dengan mekanisme `try-catch` aman tanpa `DROP CONSTRAINT IF EXISTS`.
- **Master Data & Lending Validations:**
  - Penyesuaian validasi penghapusan data master anggota dan kelompok yang memiliki relasi pinjaman aktif.
  - Dukungan pengujian single device session dan konsistensi token login.

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

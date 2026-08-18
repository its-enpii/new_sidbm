# Dokumentasi Proyek SIDBM Next

Indeks dokumentasi lengkap untuk arsitektur, basis data, billing, modul supervisi kabupaten & provinsi, pembatasan operator desa, asisten AI, laporan legacy, dan migrasi data SIDBM Next:

1. [PERBANDINGAN_SIDBM_LEGACY_VS_NEXT.md](PERBANDINGAN_SIDBM_LEGACY_VS_NEXT.md) — Dokumen analisis komparatif menyeluruh antara SIDBM Legacy (`/sidbm`) vs SIDBM Next (`/new_sidbm`), mencakup alasan upgrade, perbedaan arsitektur, basis data, modul pengawasan kabupaten & provinsi, SaaS billing, AI RAG, pengujian E2E interaktif, hingga infrastruktur.
2. [LEGACY_REPORTS_MIGRATION_ROADMAP.md](LEGACY_REPORTS_MIGRATION_ROADMAP.md) — Matriks spesifikasi dan status 100% implementasi seluruh laporan akuntansi, laporan piutang, analisis kesehatan usaha, paket LPJ tahunan, 37 dokumen perguliran pinjaman, serta laporan konsolidasi provinsi.
3. [FEATURE_ROADMAP.md](FEATURE_ROADMAP.md) — Status implementasi fitur harian, laporan akuntansi, inventaris, billing, modul supervisi kabupaten & provinsi, pembatasan scope desa, redesain landing page, suite pengujian Playwright E2E & PHPUnit, serta rincian changelog rilis.
4. [PROJECT_OVERVIEW.md](PROJECT_OVERVIEW.md) — Gambaran umum proyek, prinsip arsitektur multi-tenant sharding, sasaran, risiko, dan kriteria penyelesaian (*definition of done*).
5. [DATABASE_STRUCTURE.md](DATABASE_STRUCTURE.md) — Struktur detail skema database platform dan shard multi-tenant, pemetaan tabel legacy, pembentukan identitas ganda (`row_id` vs legacy `id`), hirarki supervisi provinsi/kabupaten, pembatasan desa (`village_row_id`), serta portabilitas SQLite/MySQL.
6. [BILLING_PAYMENT_AUTOMATION.md](BILLING_PAYMENT_AUTOMATION.md) — Spesifikasi integrasi Multi-Payment Gateway (Duitku & Tripay) (QRIS & Virtual Accounts), automatisasi tagihan perpanjangan, dan middleware pembatasan tenant overdue.
7. [ASSISTANT_INTEGRATION.md](ASSISTANT_INTEGRATION.md) — Spesifikasi integrasi Asisten AI (`enpii/assistant`), pgvector store RAG, Ollama embedding server, dan komponen chat interaktif.
8. [RBAC_MATRIX.md](RBAC_MATRIX.md) — Matriks hak akses (*Role-Based Access Control*) dan permission granular modul tenant, supervisor provinsi, supervisor kabupaten, operator desa, & platform superadmin.
9. [CUTOVER_RUNBOOK.md](CUTOVER_RUNBOOK.md) — Panduan teknis migrasi dan cutover data per tenant dari database legacy ke SIDBM Next.
10. [VALIDATION.md](VALIDATION.md) — Panduan verifikasi statis, pengujian PHPUnit backend (258 tests), dan pengujian E2E Playwright browser (47 page tests + 25 interactive CRUD tests + tests supervisi provinsi).

---

## Keputusan Arsitektur Inti

- **Topologi**: Platform Database + Multi-Tenant Shard Database (`tenant_id` column-based isolation) dengan opsi MySQL 8.4 dan SQLite support.
- **Identitas**: `row_id` sebagai PK internal teknis, `id` lama dipertahankan utuh untuk laporan & audit.
- **Akuntansi**: Double-entry journal (`journal_entries` & `journal_lines`) yang seimbang dan bersifat *immutable* — koreksi jurnal posted melalui reverse + recreate atomik (`JournalEditService`).
- **Supervisi Berjenjang (Kabupaten & Provinsi)**: Dashboard & laporan keuangan konsolidasi real-time lintas kecamatan & kabupaten (Neraca, LR, BB, Arus Kas, CALK, PDF Pack).
- **Pembatasan Operator Desa (Village Scope)**: Pengguna level desa (`is_village_user`) hanya dapat melihat dan mengelola data anggota/kelompok/proposal pinjaman milik desa bersangkutan via global scope `VillageScope`.
- **SaaS Billing**: Integrasi Tripay (Scan QRIS & 8 Virtual Account Bank) dengan scheduler auto-invoice & penangguhan otomatis tenant overdue.
- **Redesain Landing Page & Auth**: Tampilan bisnis profesional, bersih, modern, animasi smooth scroll, FAQ accordion interaktif, tanpa bocor stack teknis.
- **Automated Testing**: 100% Passed across layers (PHPUnit 258 tests/1779 assertions + Playwright 47 E2E page tests + Playwright 25 Interactive CRUD tests).
- **Infrastruktur**: Stack Dockerized lengkap (PHP-FPM 8.4, Nginx 1.29, MySQL 8.4, Redis Cache/Session/Queue Worker, PostgreSQL pgvector 16, Ollama LLM).

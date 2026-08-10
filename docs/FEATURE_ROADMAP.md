# Roadmap Fitur & Status Implementasi (SIDBM Next vs Legacy)

Tujuan: **SIDBM Next sepenuhnya menggantikan SIDBM legacy dalam operasional harian tenant dan tingkat Kabupaten.**

Referensi legacy: F:\Workspace\laragon\www\sidbm  
Dokumentasi Arsitektur: PROJECT_OVERVIEW.md, DATABASE_STRUCTURE.md, CUTOVER_RUNBOOK.md, PERBANDINGAN_SIDBM_LEGACY_VS_NEXT.md.

---

## Status Fitur & Zona (Update 2026-08-10)

| Zona | Status Next | Keterangan |
|---|---|---|
| Master Data | ✅ Selesai | Anggota, Kelompok, Lembaga, Desa + Riwayat Pinjaman |
| Alur Perguliran Pinjaman | ✅ Selesai | Proposal → Verifikasi → Alokasi → Pencairan → Angsuran |
| Jurnal & Akuntansi | ✅ Selesai | Jurnal Umum, Jurnal Angsuran, Reversal, COA Read-only |
| Cetak Struk & Kuitansi | ✅ Selesai | Struk/Kuitansi PDF langsung dari posting jurnal |
| Laporan Akuntansi Core | ✅ Selesai | Neraca, Laba Rugi, Buku Besar, Arus Kas, Perubahan Ekuitas, CALK, Kartu Angsuran |
| Laporan Piutang Core | ✅ Selesai | Portofolio Pinjaman (per desa/kelompok) & Rencana vs Realisasi |
| Tutup Buku & Alokasi Laba | ✅ Selesai | Tutup/Buka periode fiskal + Jurnal alokasi laba otomatis |
| Inventaris / Aset Tetap | ✅ Selesai | Register aset, Jurnal pembelian inventaris, Nilai buku & akumulasi penyusutan |
| Global Search | ✅ Selesai | Omnibox search header (anggota, kelompok, pinjaman, jurnal, aset) |
| SaaS Billing & Tripay | ✅ Selesai | In-app Payment Channel (QRIS & VA Bank: BCA, BRI, BNI, Mandiri, Permata, CIMB, BSI, Danamon), Auto-Invoice Scheduler, Overdue Grace Period Suspension |
| Monitoring Kabupaten (Regency) | ✅ Selesai | Dashboard Kabupaten, Laporan Konsolidasi Multi-Kecamatan (Neraca, LR, BB, Arus Kas, CALK, PDF) |
| AI Assistant & RAG | ✅ Selesai | Asisten AI (enpii/assistant) dengan Vector RAG (pgvector), Ollama, & Komponen Chat Interaktif |
| Redis Infrastructure | ✅ Selesai | Redis Cache Store, Redis Session Driver, & Dedicated Redis Queue Worker |

---

## Priotitas Eksekusi & Track Record

### P0 — Blocking Cutover Harian (DONE)
- **P0.1** Cetak bukti angsuran (Struk & Kuitansi PDF)
- **P0.2** Daftar jurnal + Reversal UI (JournalReversalService)
- **P0.3** Laporan piutang inti (Portofolio aging per desa + Rencana vs Realisasi)

### P1 — Paritas Pimpinan & Laporan Bulanan (DONE)
- **P1.1** Arus Kas (/accounting/reports/cash-flow) — Metode langsung + rekonsiliasi
- **P1.2** Perubahan Ekuitas (/accounting/reports/equity-change)
- **P1.3** CALK (/accounting/reports/calk) — Highlight otomatis + catatan kualitatif
- **P1.4** Kartu Angsuran (/lending/loans/{id}/card)

### P2 — Periodik & Infrastruktur Multi-Tenant (DONE)
- **P2.1** Tutup buku & Alokasi Laba (ProfitAllocationService)
- **P2.2** Bagan Akun Standar (COA) UI Read-Only
- **P2.3** Inventaris & Aset Tetap (AssetService)
- **P2.4** Billing Automatisasi Tripay (QRIS & Virtual Accounts) & Suspension Middleware
- **P2.5** Portal Supervisi & Konsolidasi Keuangan Kabupaten (Regency Multi-Kecamatan)

### P3 — AI, Pengalaman Pengguna & Infrastruktur (DONE)
- **P3.1** Omnibox Global Search Header
- **P3.2** Asisten AI Ariel dengan pgvector + Ollama RAG & Interactive Widgets
- **P3.3** Redis Infrastructure Integration (Cache Store, Session Driver, Background Queue Worker)

---

## Catatan Rilis & Changelog

| Tanggal | Fitur Utama yang Dirilis |
|---|---|
| 2026-07-28 | Inisialisasi P0–P3. P0.1 Cetak Struk/Kuitansi, P0.2 Reversal Jurnal, P0.3 Portofolio Pinjaman. |
| 2026-07-29 | Laporan P1 (Arus Kas, Ekuitas, CALK, Kartu Angsuran). P2.1 Tutup Buku, P2.2 COA UI, P2.3 Aset Tetap, P3.1 Global Search. |
| 2026-08-09 | Modul Integrasi Tripay (QRIS & Virtual Accounts), Automated Subscription Invoice Generator, Overdue Grace Period Suspension. |
| 2026-08-10 | Modul Portal Kabupaten (Supervisory Dashboard & Real-Time Consolidated Financial Reports untuk Neraca, Laba Rugi, Arus Kas, BB, CALK + PDF Export). |
| 2026-08-10 | Migrasi penuh ke Redis Session Driver, Redis Queue Worker (
ew_sidbm-queue-1), serta refactoring Vue UI ke shared composables (useMoney, usePeriodOptions) dan komponen UI terstandar (SmartSelect, ReportPeriodFilter, AppRadioGroup). |

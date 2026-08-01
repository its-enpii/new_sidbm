# Roadmap fitur (paritas harian vs legacy)

Tujuan: **Next mengganti SIDBM legacy di kerja harian 1 tenant pilot** (`local` / suffix `1`).

Bukan tujuan fase ini: mirror 1:1 semua sub-laporan legacy, multi-tenant mass cutover, atau inventaris penuh.

Referensi legacy (baca alur, **bukan** copy exact): `F:\Workspace\laragon\www\sidbm`  
— route `web.php`, `TransaksiController`, `PelaporanController`, views `transaksi/dokumen/*`, `pelaporan/view/*`.

Docs arsitektur: `PROJECT_OVERVIEW.md` workstream A–F, `DATABASE_STRUCTURE.md`, `CUTOVER_RUNBOOK.md` (data).

---

## Metric “siap ganti legacy” (pilot)

1. **1 hari kasir** tanpa buka app lama: angsuran → cetak bukti → koreksi jurnal.  
2. **1 minggu petugas** tanpa app lama: pantau tunggakan / LPP / kolek ringkas.  
3. Data pilot recon tetap hijau (lihat `CUTOVER_RUNBOOK.md`).

---

## Smoke pilot `local` (2026-07-29)

Script: `docker exec new_sidbm-app-1 php scripts/smoke_pilot_local.php`

| Check | Result |
|---|---|
| Data counts (members/groups/loans/journals) | OK |
| P0.1 receipt (legacy `Angs.` + Next `loan_installment`) | OK after fix |
| P0.2 journal browse + reverse candidates | OK |
| P0.3 portfolio + schedule-vs-actual | OK |
| P1.1–P1.4 cash/equity/calk/card | OK |
| Routes registered | OK |

**Known non-blockers (data quality, not missing feature):**

- ~~`groups.organization_unit_row_id` mostly null~~ → fixed 2026-07-29 via `legacy:sync-villages` (20 desa incl. custom codes; 566 groups + 1077 members linked).
- Migrated journals stay `source_type=legacy_transaksi` (expected); receipt detects `Angs.` + `legacy_loan_id`.
- `loan_payments.journal_entry_row_id` null on migrate (payments loaded without journal link).

---

## Status ringkas (2026-07-29)

| Zona | Next | Blocking harian? |
|---|---|---|
| Master data | Ada + **detail** anggota/kelompok/lembaga + riwayat pinjaman | Tidak |
| Alur pinjaman (proposal→cair→bayar) | Ada | Tidak (depth form beda) |
| Jurnal angsuran + umum (create) | Ada | — |
| **Cetak bukti angsuran** | ✅ P0.1 | — |
| **Daftar + reverse jurnal** | ✅ P0.2 | — |
| Laporan akuntansi NS/Neraca/LR/BB/Jurnal | Ada + PDF | Tidak |
| **Laporan piutang inti** | ✅ P0.3 (portofolio group-by-desa + R vs R) | — |
| Arus kas / LPM / CALK / kartu angsuran | ✅ P1 | — |
| Tutup buku | ✅ P2.1 close/reopen + year openings + **alokasi laba** | — |
| COA UI | ✅ P2.2 read-only (mutasi pusat) | — |
| Aset / inventaris UI | ✅ P2.3 register + nilai buku | — |
| Migrasi data pilot | Hijau | Bukan fitur harian |
| Tenant #2+ | Belum cutover data; **provision blank hijau** (admin) | P2.4 setelah metric pilot |
| Admin platform | ✅ tenant/plan/invoice + **provision + role** | COA CRUD ad-hoc / impersonate later |

---

## Prioritas eksekusi (wajib berurutan)

### P0 — Blocking cutover harian

| ID | Fitur | Done when | Legacy ref |
|---|---|---|---|
| **P0.1** | Cetak bukti angsuran (kuitansi/struk) | ✅ Setelah simpan: tombol **Cetak Bukti** → PDF; reprint `GET …/journal-entries/{row_id}/installment-receipt` | `transaksi/jurnal_angsuran/dokumen/struk*`, `kuitansi*` |
| **P0.2** | Daftar jurnal + reverse | ✅ `/accounting/journals` list posted + reverse UI → `JournalReversalService`; link bukti angsuran | `transaksi/reversal`, list jurnal |
| **P0.3** | Laporan piutang inti | ✅ Portofolio (aging + tunggakan pokok/jasa + per desa) + **Rencana vs Realisasi** `/lending/reports/schedule-vs-actual` | `perkembangan_piutang/*` subset |

**P0.3 — subset yang dikerjakan (bukan clone):**

1. Tunggakan / jatuh tempo (perkuat portofolio + filter)  
2. LPP per kelompok (posisi pinjaman)  
3. Kolektibilitas ringkas (aging sudah ada → per desa/kelompok)  
4. Rencana vs realisasi (1 laporan)

**Skip P0.3:** varian mingguan × v2, cadangan penghapusan, rekap proposal/waiting (sudah di tab Tahapan Perguliran).

### P1 — Paritas pimpinan / bulanan

| ID | Fitur |
|---|---|
| P1.1 | ✅ Arus Kas `/accounting/reports/cash-flow` — metode langsung dari jurnal kas, rekonsiliasi opening+net=closing |
| P1.2 | ✅ Perubahan Ekuitas `/accounting/reports/equity-change` — bridge opening→laba→mutasi→closing |
| P1.3 | ✅ CALK `/accounting/reports/calk` — highlights otomatis + catatan editable |
| P1.4 | ✅ Kartu angsuran `/lending/loans/{id}/card` — dari detail pinjaman |

### P2 — Periodik / non-harian

| ID | Fitur |
|---|---|
| P2.1 | ✅ Tutup buku — `/accounting/period-close`: close/reopen, year openings, alokasi laba → jurnal `profit_allocation` (2.1.04.01/02/03 + 3.2.01.01). |
| P2.2 | ✅ COA UI read-only `/accounting/chart-of-accounts` — filter jenis/status/cari; mutasi akun = pusat |
| P2.3 | ✅ Inventaris: **beli** = Jurnal Umum `pembelian_inventaris`; **register** `/accounting/assets` list/detail/status/nilai buku. |
| P2.4 | Cutover tenant #2+ (setelah P0 pilot hijau) |

### P3 — Bukan blocking

| ID | Fitur |
|---|---|
| P3.1 | ✅ Search global header — `/search?q=` anggota/kelompok/pinjaman/jurnal/inventaris |
| P3.2 | Lonceng notifikasi in-app |
| P3.3 | Holding/kab multi-tenant reports, 1:1 sub-laporan legacy |

---

## Workstream docs ↔ fitur

| Workstream | Fitur status |
|---|---|
| A Foundation | hijau |
| B Membership | hijau ops + detail entity + riwayat pinjaman |
| C Accounting | posting/report/list/reverse/tutup+alokasi+COA view hijau |
| D Lending | lifecycle + cetak + portofolio/RvsR/kartu hijau |
| E Supporting | budget/settings/inventaris hijau; docs depth P3 |
| F Migration | pilot hijau — **bukan** yang nahan user pindah |

---

## Aturan kerja

1. **Frekuensi > kelengkapan.** Harian dulu.  
2. **Legacy = referensi alur & field**, bukan pixel/CSS copy.  
3. **Jangan silent-fix data kotor** (sama prinsip migrasi).  
4. **Tenant baru / Phase 6** hanya setelah metric § “siap ganti legacy”.  
5. Satu P0 selesai + smoke pilot sebelum loncat P1.

---

## Changelog roadmap

| Tanggal | Perubahan |
|---|---|
| 2026-07-28 | Initial: P0–P3 dari audit legacy routes + Next nav + workstream docs. Portofolio pinjaman (thin) sudah ada di `/lending/reports/portfolio`. |
| 2026-07-28 | P0.1 cetak bukti angsuran. P0.2 daftar + reverse jurnal. |
| 2026-07-28 | P0.3 portofolio diperkaya + rencana vs realisasi. |
| 2026-07-28 | P1.1–P1.4: arus kas, perubahan ekuitas, CALK, kartu angsuran. |
| 2026-07-29 | Detail master data (anggota/kelompok/lembaga) + `EntityLoanHistoryService` + `LoanHistoryTable`. |
| 2026-07-29 | Portofolio: group-by-desa (header + total per desa + spacer); PDF sama. |
| 2026-07-29 | Sync status workstream C/D (P0+P1 hijau). |
| 2026-07-29 | **P2.1** tutup buku + alokasi laba (`ProfitAllocationService`). Next: P2.2 COA UI. |
| 2026-07-29 | **P2.2** COA read-only (aturan pusat: no CRUD tenant). Next: P2.3 aset UI. |
| 2026-07-29 | **P2.3** inventaris: beli via jurnal umum; `/assets` register/nilai buku/status (`AssetService`). |
| 2026-07-29 | **P3.1** search global header (`GlobalSearchService` + omnibox). |
| 2026-07-29 | **Admin platform:** provision lengkap (COA + fiscal + loan products + system roles + role `admin` user pertama); assign role user tenant; `TenantWorkbench` + tombol **Lengkapi provision**. |


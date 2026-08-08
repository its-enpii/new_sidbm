# Dokumen Pinjaman — Tracker Progres Porting

> Progres porting dokumen pinjaman dari `F:\Workspace\laragon\www\sidbm`
> (legacy ~40 view dokumen di `resources/views/perguliran/dokumen/`) ke
> `new_sidbm` (sistem baru di `resources/views/reports/pdf/loan_documents/`).

## Status

- ✅ = Selesai di `new_sidbm`
- 🚧 = Dalam proses
- ⏳ = Belum diimplementasi (target iterasi berikutnya)
- 🗄️ = Tidak relevan di `new_sidbm` (sudah diganti/terintegrasi)

## Konvensi

- Entry pada registry `LoanDocumentService::DOCUMENTS`
  (`F:\workspace\laragon\www\new_sidbm\app\Domain\Lending\Services\Reports\LoanDocumentService.php`).
- View Blade di `resources/views/reports/pdf/loan_documents/<key>.blade.php`.
- Tanda tangan dinamis via `SignatureTemplateService` dengan key
  `default` | `proposal` | `perjanjian_kredit` | `kwitansi`.
- Stage = `proposal` | `verification` | `disbursement` | `attachment`.

## Stage Mapping (status loan → dokumen yang tersedia)

| Stage | Loan status |
|---|---|
| `proposal` | draft, verified, waiting, approved, active, disbursed, completed |
| `verification` | verified, waiting, approved, active, disbursed, completed |
| `disbursement` | waiting, approved, active, disbursed, completed |

## Iterasi 1 — 10 Dokumen (✅ selesai)

| # | Key | Stage | Signature | Modern view |
|---|---|---|---|---|
| 1 | `cover_proposal` | proposal | — | `reports.pdf.loan_documents.cover_proposal` |
| 2 | `pengajuan_kredit` | proposal | `proposal` | `reports.pdf.loan_documents.pengajuan_kredit` |
| 3 | `profil_kelompok` | proposal | — | `reports.pdf.loan_documents.profil_kelompok` |
| 4 | `susunan_pengurus` | proposal | — | `reports.pdf.loan_documents.susunan_pengurus` |
| 5 | `daftar_pemanfaat` | proposal | `proposal` | `reports.pdf.loan_documents.daftar_pemanfaat` |
| 6 | `pernyataan_tanggung_renteng` | proposal | `default` | `reports.pdf.loan_documents.pernyataan_tanggung_renteng` |
| 7 | `rekomendasi_kredit` | verification | `proposal` | `reports.pdf.loan_documents.rekomendasi_kredit` |
| 8 | `spk` | disbursement | `perjanjian_kredit` | `reports.pdf.loan_documents.spk` |
| 9 | `berita_acara_pencairan` | disbursement | `perjanjian_kredit` | `reports.pdf.loan_documents.berita_acara_pencairan` |
| 10 | `kuitansi_pencairan` | disbursement | `kwitansi` | `reports.pdf.loan_documents.kuitansi_pencairan` |

## Iterasi 2 — Proposal Detail & Verifikasi (✅ selesai)

| # | Key | Stage | Signature | Modern view |
|---|---|---|---|---|
| 11 | `check` | proposal | — | `reports.pdf.loan_documents.check` |
| 12 | `ba_musyawarah` | verification | `default` | `reports.pdf.loan_documents.ba_musyawarah` |
| 13 | `surat_verifikasi` | verification | `proposal` | `reports.pdf.loan_documents.surat_verifikasi` |
| 14 | `surat_kelayakan` | verification | `proposal` | `reports.pdf.loan_documents.surat_kelayakan` |
| 15 | `form_verifikasi` | verification | — | `reports.pdf.loan_documents.form_verifikasi` |
| 16 | `form_verifikasi_anggota` | verification | — | `reports.pdf.loan_documents.form_verifikasi_anggota` |

## Iterasi 3 — Pencairan & Penyaluran (✅ selesai)

| # | Key | Stage | Signature | Modern view |
|---|---|---|---|---|
| 17 | `cover_pencairan` | disbursement | — | `reports.pdf.loan_documents.cover_pencairan` |
| 18 | `rencana_angsuran` | disbursement | — | `reports.pdf.loan_documents.rencana_angsuran` |
| 19 | `kartu_angsuran_anggota` | disbursement | — | `reports.pdf.loan_documents.kartu_angsuran_anggota` |
| 20 | `tanda_terima` | disbursement | `default` | `reports.pdf.loan_documents.tanda_terima` |
| 21 | `pemberitahuan_desa` | disbursement | `default` | `reports.pdf.loan_documents.pemberitahuan_desa` |
| 22 | `ba_pendanaan` | disbursement | `perjanjian_kredit` | `reports.pdf.loan_documents.ba_pendanaan` |
| 23 | `peserta_asuransi` | disbursement | — | `reports.pdf.loan_documents.peserta_asuransi` |
| 24 | `kuitansi_anggota` | disbursement | `kwitansi` | `reports.pdf.loan_documents.kuitansi_anggota` |
| 25 | `tagihan` | disbursement | `kwitansi` | `reports.pdf.loan_documents.tagihan` |
| 26 | `surat_ahli_waris` | disbursement | `default` | `reports.pdf.loan_documents.surat_ahli_waris` |
| 27 | `surat_kuasa` | disbursement | `default` | `reports.pdf.loan_documents.surat_kuasa` |

> 🗄️ `kartu_angsuran` (loan-level) sudah ter-cover oleh `reports.pdf.loan_card` (LoanCardService).

## Iterasi 4 — Penunjang (✅ selesai)

| # | Key | Stage | Signature | Modern view |
|---|---|---|---|---|
| 28 | `anggota` | proposal | — | `reports.pdf.loan_documents.anggota` |
| 29 | `ktp` | proposal | — | `reports.pdf.loan_documents.ktp` |
| 30 | `catatan_bimbingan` | proposal | — | `reports.pdf.loan_documents.catatan_bimbingan` |
| 31 | `daftar_hadir_verifikasi` | verification | — | `reports.pdf.loan_documents.daftar_hadir_verifikasi` |
| 32 | `tanggung_renteng_kematian` | disbursement | `default` | `reports.pdf.loan_documents.tanggung_renteng_kematian` |
| 33 | `iptw` | disbursement | `default` | `reports.pdf.loan_documents.iptw` |
| 34 | `rekening_koran` | disbursement | — | `reports.pdf.loan_documents.rekening_koran` |
| 35 | `pernyataan_peminjam` | disbursement | `default` | `reports.pdf.loan_documents.pernyataan_peminjam` |
| 36 | `daftar_hadir_pencairan` | disbursement | — | `reports.pdf.loan_documents.daftar_hadir_pencairan` |

> 🗄️ `pemanfaat` sudah ter-cover oleh `daftar_pemanfaat` (Iterasi-1).

## Referensi Arsitektur

### Backend
- `app/Domain/Lending/Services/Reports/LoanDocumentService.php` — registry, payload, token.
- `app/Http/Controllers/Lending/LoanDocumentController.php` — endpoint PDF.
- `routes/web.php` line ~180 — route `lending.loans.documents.print`.
- `app/Domain/Documents/Services/SignatureTemplateService.php` — template tanda tangan.

### Frontend
- `resources/js/Pages/Lending/Loans/Show.vue` — tombol + modal.

### Legacy Referensi
- `F:\Workspace\laragon\www\sidbm\resources\views\perguliran\dokumen\` — view sumber.
- `F:\Workspace\laragon\www\sidbm\app\Utils\Pinjaman.php` — 24 placeholder legacy.
- `F:\Workspace\laragon\www\sidbm\app\Http\Controllers\PinjamanKelompokController.php` line 1486 — alur cetak.

## Token Tanda Tangan (Modern Naming)

| Group | Token | Sumber |
|---|---|---|
| Lembaga | `{nama_lembaga}`, `{nama_singkat}`, `{alamat_lembaga}`, `{telepon_lembaga}`, `{email_lembaga}` | `OrganizationProfile` |
| Kelompok | `{nama_kelompok}`, `{kd_kelompok}`, `{alamat_kelompok}`, `{desa}`, `{kecamatan}` | `Group` + village |
| Pengurus aktif | `{nama_ketua}`, `{nama_sekretaris}`, `{nama_bendahara}` | `GroupOfficer` (active) |
| Pinjaman | `{produk}`, `{no_pinjaman}`, `{alokasi}`, `{jasa_persen}`, `{jangka}`, `{tgl_proposal}`, `{tgl_verifikasi}`, `{tgl_cair}`, `{tgl_kondisi}`, `{no_spk}`, `{keterangan_verifikasi}` | `Loan` |
| Pengurus snapshot | `{ketua_pengurus}`, `{sekretaris_pengurus}`, `{bendahara_pengurus}` | `LoanCommittee` |
| Pemanfaat (first) | `{pemanfaat_nama}`, `{pemanfaat_nik}`, `{pemanfaat_penjamin}`, `{pemanfaat_alokasi}` | `LoanBeneficiary` |

## Verifikasi

1. `php artisan test --filter=LoanDocumentTest`
2. Buka `/lending/loans/{loan}` untuk status `draft`/`verified`/`waiting` → klik **Cetak Dokumen** → PDF terbuka.
3. Settings → Tanda Tangan → tab "Proposal" → isi `<p>Bagian Kredit — {{produk}}</p>` → Simpan → cetak ulang → token ter-replace.
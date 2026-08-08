# Cash Evidence (BKM / BKK / BM) — Progress Tracker

## Latar Belakang

SIDBM Next (`F:\workspace\laragon\www\new_sidbm`) sudah memiliki arsitektur
double-entry akunting modern (`journal_entries` + `journal_lines`, `accounts`,
`JournalPostingService`, `JournalReversalService`, `FiscalPeriodCloseService`).
SIDBM legacy `F:\Workspace\laragon\www\sidbm` memiliki tiga bukti kas cetak:

| Dokumen | Singkatan | Heuristik debit × kredit                                                                           |
| ------- | --------- | -------------------------------------------------------------------------------------------------- |
| Bukti Kas Masuk | BKM | debit `1.1.01.*` & kredit non-kas — ATAU — debit `1.1.02.*` & kredit non-kas/bank |
| Bukti Kas Keluar | BKK | debit non-kas & kredit `1.1.01.*`                                                                  |
| Bukti Memorial | BM | kombinasi lain (kas↔kas, atau di luar kas & bank)                                                  |

Iterasi 1 (opsi A): **cetak bukti kas saja**, tanpa mengubah alur posting jurnal.

## Iterasi 1 — Status

| Item                                       | Status | File                                                                                                                                                  |
| ------------------------------------------ | ------ | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| Heuristik classifier                       | ✅     | `app/Domain/Accounting/Services/Reports/DocumentKindClassifier.php`                                                                                    |
| Service builder payload bukti              | ✅     | `app/Domain/Accounting/Services/Reports/CashEvidenceService.php`                                                                                      |
| Controller HTTP                            | ✅     | `app/Http/Controllers/Accounting/CashEvidenceController.php`                                                                                          |
| Routes                                     | ✅     | `routes/web.php` (`accounting.journals.cash-evidence`, `cash-evidence-kind`, `cash-evidence.kind-explicit`)                                            |
| View Blade bersama                         | ✅     | `resources/views/reports/pdf/cash_evidence/_document.blade.php` (layout box 14×9cm)                                                                    |
| View pointer BKM / BKK / BM                | ✅     | `resources/views/reports/pdf/cash_evidence/{bkm,bkk,bm}.blade.php` (3 baris; hanya set `$kind` lalu include `_document`)                              |
| Tombol "Bukti" di daftar jurnal            | ✅     | `resources/js/Pages/Accounting/Journals/Index.vue` (label BKM/BKK/BM sesuai kind)                                                                     |
| `cash_evidence_*` di payload Browse        | ✅     | `app/Domain/Accounting/Services/JournalBrowseService.php`                                                                                              |
| Permission boundary                        | ✅     | `journals.view` pada seluruh endpoint controller                                                                                                       |

## Endpoints

| Method | URI                                                          | Name                                          | Output                                                              |
| ------ | ------------------------------------------------------------ | --------------------------------------------- | ------------------------------------------------------------------- |
| GET    | `/accounting/journals/{entry}/cash-evidence`                 | `accounting.journals.cash-evidence`           | PDF bukti kas (kind ditentukan otomatis dari debit/kredit)         |
| GET    | `/accounting/journals/{entry}/cash-evidence/{kind}`          | `accounting.journals.cash-evidence.kind-explicit` | PDF bukti kas dengan kind eksplisit (BKM / BKK / BM)                |
| GET    | `/accounting/journals/{entry}/cash-evidence-kind`            | `accounting.journals.cash-evidence.kind`      | JSON `{ kind, row_id, id }`                                         |

`{kind}` di-where dengan regex `[A-Z]{3}` (BKM / BKK / BM saja).

## Pemetaan Legacy → Modern (tidak disalin persis)

| Konsep legacy                                          | Padanan modern                                                                  |
| ------------------------------------------------------ | ------------------------------------------------------------------------------- |
| `transaksi_{tenant}.idt`                               | `journal_entries.id`                                                            |
| `transaksi_{tenant}.tgl_transaksi`                     | `journal_entries.transaction_date`                                              |
| `transaksi_{tenant}.rekening_debit`/`rekening_kredit`  | `journal_lines.account_row_id` → `accounts.code`                                |
| `transaksi_{tenant}.relasi`                            | `journal_entries.legacy_relation`                                               |
| `transaksi_{tenant}.keterangan_transaksi`              | `journal_entries.description`                                                   |
| `transaksi_{tenant}.jumlah`                            | `journal_lines.debit` (atau `credit` jika debit 0)                              |
| `TransaksiController@bkm`/`bkk`/`bm` (legacy controller) | `CashEvidenceController@document` (modern controller)                          |
| View `transaksi/dokumen/bkm.blade.php` (legacy)        | `resources/views/reports/pdf/cash_evidence/bkm.blade.php` (modern, via include) |
| Heuristik `App\Utils\Keuangan::startWith`              | `DocumentKindClassifier::classify($debitCode, $creditCode)`                     |

## Yang BELUM di iterasi 1

- **Form entry kas masuk/keluar/memorial sebagai transaction_type baru** — saat ini hanya general JE yang dipakai (`JournalEntryController::create`), dengan `transaction_type = aset_masuk / aset_keluar / dll`.
- **Bulk cetak bukti (cetak.blade.php)** — iterasi berikutnya.
- **Peran tanda tangan dinamis dari Settings** — saat ini placeholder kosong `( ……………… )`. Iterasi berikutnya, baca dari `SignatureTemplateService` atau `tenant_settings.signatures`.
- **Buku Kas register running-balance per akun kas** — `CashFlowService` saat ini adalah cash-flow statement, bukan buku kas.

## Verifikasi

```bash
php -l app/Domain/Accounting/Services/Reports/DocumentKindClassifier.php
php -l app/Domain/Accounting/Services/Reports/CashEvidenceService.php
php -l app/Http/Controllers/Accounting/CashEvidenceController.php
php -l app/Domain/Accounting/Services/JournalBrowseService.php
php -l resources/views/reports/pdf/cash_evidence/{bkm,bkk,bm,_document}.blade.php
php artisan route:list | grep cash-evidence
```

Manual UI:
- Login → `/accounting/journals` → klik tombol BKM/BKK/BM pada baris jurnal → PDF bukti kas terbuka di tab baru.
- Coba dengan jurnal angsuran (otomatis BKM dari debit `1.1.01.*` + kredit piutang).
- Coba dengan jurnal `aset_keluar` (otomatis BKK).
- Coba dengan jurnal `pemindahan_saldo` (kas↔kas → otomatis BM).
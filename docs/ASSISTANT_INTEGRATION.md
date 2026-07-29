# Asisten Encompletion × SIDBM

Asisten chat ditanam di SIDBM lewat **encompletion embed mode**.  
Encompletion = brain + chat UI. SIDBM = source of truth + RBAC + domain.

## Alur

```
User login SIDBM
  → GET /api/assistant/embed-token   (web session auth + tenant + assistant.use)
  → widget.js + embed_token
  → chat SSE di encompletion
  → tool call Kategori B
  → POST /api/assistant/tools/{tool}  (HMAC)
       body: { tool, external_user_id, params, ts }
  → SIDBM: verifikasi sig → resolve user → cek permission → Domain service
```

`external_user_id` = `users.row_id` (platform).

## Env SIDBM

```env
ENCOMPLETION_BASE_URL=http://localhost:8010
ENCOMPLETION_TENANT_API_KEY=tk_...   # plaintext dari admin embed encompletion
ENCOMPLETION_WIDGET_ENABLED=true
# multi-tenant satu host:
TENANCY_ALLOW_HEADER=true
```

## RBAC

- Katalog: `config/permissions.php`
- User **tanpa** role → full access (legacy)
- User **dengan** role → union permission role packs
- Superadmin → full
- FormRequest kritis pakai trait `AuthorizesPermission`
- Tool map: `permissions.tool_map.*`

Assign role (setelah tenant context hidup):

```php
app(\App\Domain\Access\Services\PermissionChecker::class)
    ->assignRole($user, 'kasir');
```

## Endpoint tool

| Tool | Permission | Write? |
|---|---|---|
| `search_members` | members.view | no |
| `search_groups` | groups.view | no |
| `search_loans` | loans.view | no |
| `get_loan` | loans.view | no |
| `list_accounts` | journals.view | no |
| `search_journals` | journals.view | no |
| `search_assets` | journals.view | no |
| `get_asset` | journals.view | no |
| `list_due_billing` | messages.send | no |
| `create_journal_entry` | journals.create | **yes** |
| `reverse_journal` | journals.create | **yes** |
| `record_installment` | installments.record | **yes** |
| `send_billing_notices` | messages.send | **yes** |

### Reaktif (bukan form flat)

Asisten **lookup dulu**, tanya jika tidak jelas / ambigu:

1. NL → tool read (`search_*` / `list_accounts` / `search_journals`)  
2. `match_count === 1` → pakai  
3. `needs_clarification` / `match_count ≠ 1` → tampilkan `candidates` — **jangan tebak**  
4. Write tool **tanpa** `confirm` → `preview: true` (rencana saja)  
5. User setuju → panggil ulang `proposed_params` + `confirm: true` (+ konfirmasi UI encompletion)

#### Preview write

Tanpa `confirm` / `confirm=false`:

```json
{
  "preview": true,
  "needs_confirmation": true,
  "action": "record_installment",
  "summary": "…",
  "plan": { },
  "warnings": ["Kelebihan bayar …"],
  "options": [
    {"id": "apply_excess_to_principal", "label": "…"},
    {"id": "cap_to_due", "label": "…"},
    {"id": "cancel", "label": "…"}
  ],
  "proposed_params": { "confirm": true, "…": "…" }
}
```

Kelebihan angsuran: user pilih `allocation_choice` lalu `confirm=true`.

Lookup response shape:

```json
{ "items": [...], "match_count": 2, "needs_clarification": true }
```

Write tools juga bisa mengembalikan shape yang sama (mis. bank ambigu, multi loan) tanpa error 422.

**COA kas (struktural, semua tenant):**

| Kode | Arti |
|---|---|
| `1.1.01.*` | Kas / setara kas |
| `1.1.01.01` | Kas tunai/umum |
| `1.1.01.02` | Kas kecil |
| `1.1.01.03`–`99` | Biasanya bank (nama mengikuti COA tenant) |

Jangan hardcode merek bank (BCA/Jateng/…). Cocokkan `list_accounts` ke **nama akun tenant**.

Base URL tool (daftar di encompletion per tenant):

```
{APP_URL}/api/assistant/tools/{tool_name}
```

Atau single dispatcher:

```
POST {APP_URL}/api/assistant/tools
body.tool = "search_members"
```

Header wajib dari encompletion:

- `X-Encompletion-Signature`
- `X-Encompletion-Timestamp`
- `X-Encompletion-Key-Hash` (opsional, cross-check)

Opsional: `X-Tenant-Code` jika `TENANCY_ALLOW_HEADER=true`.

## Daftar tool di encompletion

Admin embed → tenant SIDBM → tools. Contoh JSON schema:

### search_members
`query` (+ opsional `group_query`). `requires_confirmation`: false

### search_groups
`query` nama/kode kelompok.

### search_loans
`group_query` / `member_query` / `loan_number`. Item memuat `next_installment`.

### get_loan
`loan_row_id` → detail + `next_installment` + `group_members`.

### list_accounts
`code_prefix`, `query` (nama COA), `cash_only`.

### search_assets / get_asset

Register inventaris (bukan form beli). `query` nama/kode → items + `book_value`. Detail: `asset_row_id` + opsional `as_of`.

### create_journal_entry  (requires_confirmation: true)

- Beli aset: NL *"beli motor …"* → `pembelian_aset_kendaraan` (atau tanah/gedung/peralatan) + `asset_name`  
  Debit COA `1.2.01.0x` per jenis; register row `assets` ikut jurnal.
- Setor bank: *"setor ke Bank X"* → `pemindahan_saldo` + `bank_account_query`  
  Resolve debit = `1.1.01.03+` cocok nama COA; credit default Kas Tunai.  
  Nama bank tidak di COA → `needs_clarification` + daftar akun bank tenant.
- **Tanggal relatif** dihitung encompletion (`Today's date is …`) → `YYYY-MM-DD`. SIDBM tidak parse NL tanggal.

### search_journals
Cari jurnal **posted** (koreksi/duplikat). Filter: tanggal, amount, type, `account_query`, description, `recent`. Default exclude yang sudah di-reversal. Tag `possible_duplicate_of` jika fingerprint (tanggal+amount+type) sama.

### reverse_journal  (requires_confirmation: true)

Posted journal **immutable** — tidak edit/hapus. Koreksi = jurnal balik (+ opsional post ulang).

| NL | Alur |
|---|---|
| Salah akun (Ops harusnya SPP) | `search_journals` → `reverse_journal` `wrong_account_query` + `correct_bank_account_query` + `repost=true` |
| Duplikat barusan | `search_journals` recent/amount → pilih yang salah → `reverse_journal` **tanpa** repost |
| Angsuran salah kelompok (Indah→Sari) | `search_journals` `group_query`/`installments_only` → `reverse_journal` `wrong_group_query` + `correct_group_query`/`correct_loan_id` + repost (pecahan dari lines asli) |
| Angsuran barusan salah, target belum jelas | reverse dulu → tanya kelompok/pinjaman benar → post `record_installment` |

### record_installment  (requires_confirmation: true)

Boleh flat IDs **atau** NL-friendly:

- `transaction_date` + `total_amount` + `member_query` + `group_query`
- Server: cari loan (kelompok dulu; pinjaman kelompok sering `member_row_id` null), pecah total → bunga dulu lalu pokok dari jadwal, kas default Tunai, `reference` = penyetor
- Multi loan / multi anggota → `needs_clarification` + candidates

### send_billing_notices  (requires_confirmation: true)
```json
{
  "type": "object",
  "required": ["due_date", "installment_row_ids"],
  "properties": {
    "due_date": { "type": "string" },
    "installment_row_ids": { "type": "array", "items": { "type": "integer" } }
  }
}
```

Capability profile tenant embed:

- `allow_bash`: **false**
- `allow_artifact_generation`: false (atau true jika perlu laporan)
- whitelist hanya tool di atas

Persona: proaktif — lookup dulu, klarifikasi hanya jika `needs_clarification`.  
Dump schema: `php artisan sidbm:assistant-tools --base=http://host.docker.internal:8081`

## Setup checklist

1. Encompletion: tenant embed + API key → `ENCOMPLETION_TENANT_API_KEY`
2. Daftarkan **13** tools (lihat `sidbm:assistant-tools`)
3. Capability: bash off; confirmation on write; whitelist semua tool id
4. SIDBM `.env`: base/public URL + key + `WIDGET_ENABLED=true`
5. Login → FAB asisten
6. Uji read (`search_groups` / `search_loans`) dulu, baru write

## Keamanan

- Tenant API key **tidak** ke browser; hanya embed_token short-lived
- Tool call ditandatangani HMAC; secret = `sha256(tenant_api_key)`
- Aksi dijalankan sebagai user pemberi perintah (bukan service account)
- Posted journal tetap immutable di domain layer

# SIDBM Next

Aplikasi pembaruan SIDBM berbasis Laravel 13, Vue 3, Inertia 2, Tailwind CSS 4, MySQL multi-tenant shard, Redis (Cache, Session, Queue), PostgreSQL (pgvector RAG), Ollama LLM, dan Docker.

## Stack Utama

- **Backend**: PHP 8.4 + Laravel 13
- **Frontend**: Vue 3.5 + Inertia 2.0 + Tailwind CSS 4 + Vite 7
- **Database Utama**: MySQL 8.4 — 1 database platform + multiple 	enant shard
- **Vector Store (RAG)**: PostgreSQL 16 + pgvector
- **Cache, Session & Queue**: Redis 8 (predis) + Background Queue Worker
- **AI Engine**: Local Ollama LLM / Embeddings (
omic-embed-text)
- **Server / Proxy**: Nginx 1.29 + PHP-FPM 8.4

## Modul Utama

1. **Multi-Tenant Sharding Engine** — Penanganan multi-tenant skala besar (500+ tenant) tanpa suffix tabel dinamis.
2. **Portal Pengawasan Kabupaten (Regency Supervisory)** — Konsolidasi keuangan gabungan seluruh kecamatan (Neraca, Laba Rugi, Buku Besar, Arus Kas, CALK, PDF).
3. **Automatisasi SaaS Billing & Tripay Payment** — Pembayaran tagihan langganan via QRIS & Virtual Account Bank (BCA, BRI, BNI, Mandiri, Permata, CIMB, BSI, Danamon), auto-invoice scheduler, dan pembatasan otomatis tenant *overdue*.
4. **AI Assistant & Embedded RAG** — Asisten AI internal (enpii/assistant) dengan pencarian vektor dokumen RAG dan komponen interaktif chat.

## Menjalankan dengan Docker

`ash
cp .env.example .env
docker compose up --build -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate \
  --database=platform \
  --path=database/migrations/platform --force
docker compose exec app php artisan sidbm:bootstrap-local --password='change-me'
`

sidbm:bootstrap-local bersifat idempotent: mendaftarkan shard+tenant local, migrasi shard, sinkronisasi registry, import COA, buka periode fiskal, seed master data/produk pinjaman, dan provision user dev.

Aplikasi: <http://localhost:8080/login> (atau port APP_PORT di .env)  
Vite HMR: port VITE_PORT (default 5173 / 5174)  
Login dev: dev / change-me (atau password bootstrap).

MySQL host port default **3307** (FORWARD_DB_PORT) agar tidak bentrok dengan Laragon MySQL di 3306. Di dalam Docker, app terhubung ke mysql:3306.

## Frontend & Build

`ash
# Menjalankan build frontend untuk produksi:
npm run build

# Menjalankan dev server Vite:
npm run dev

# Menjalankan E2E Test (Playwright):
npm run e2e
`

## Pengujian Automated (PHPUnit & Integration)

Pengujian standar tanpa integrasi MySQL:

`ash
docker compose exec app php artisan test
`

Pengujian integrasi tenancy & konsolidasi kabupaten (menggunakan database test):

`ash
docker compose exec \
  -e RUN_TENANCY_INTEGRATION_TESTS=true \
  app php artisan test
`

## Dokumentasi Terkait

- **Dokumen Perbandingan dengan SIDBM Legacy**: [docs/PERBANDINGAN_SIDBM_LEGACY_VS_NEXT.md](docs/PERBANDINGAN_SIDBM_LEGACY_VS_NEXT.md)
- **Arsitektur & Topologi Database**: [docs/PROJECT_OVERVIEW.md](docs/PROJECT_OVERVIEW.md) & [docs/DATABASE_STRUCTURE.md](docs/DATABASE_STRUCTURE.md)
- **Billing Automation & Tripay**: [docs/BILLING_TRIPAY_AUTOMATION.md](docs/BILLING_TRIPAY_AUTOMATION.md)
- **Asisten AI & RAG**: [docs/ASSISTANT_INTEGRATION.md](docs/ASSISTANT_INTEGRATION.md)
- **Matrix RBAC & Hak Akses**: [docs/RBAC_MATRIX.md](docs/RBAC_MATRIX.md)
- **Runbook Migrasi Data**: [docs/CUTOVER_RUNBOOK.md](docs/CUTOVER_RUNBOOK.md)

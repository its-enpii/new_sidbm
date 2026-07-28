# SIDBM Next

Aplikasi pembaruan SIDBM berbasis Laravel 13, Vue 3, Inertia, Tailwind CSS, MySQL multi-tenant shard, Redis, dan Docker.

## Stack

- PHP 8.4 + Laravel 13
- Vue 3 + Inertia 2
- Tailwind CSS 4 + Vite 7
- MySQL 8.4: satu database platform dan beberapa tenant shard
- Redis 8
- Nginx + PHP-FPM

## Menjalankan dengan Docker

```bash
cp .env.example .env
docker compose up --build -d
docker compose exec app php artisan key:generate
```

Aplikasi: <http://localhost:8081> (ubah `APP_PORT` jika port ini tidak tersedia)
Vite HMR: <http://localhost:5173>

Authentication development:

```bash
docker compose exec app php artisan sidbm:provision-dev-user --password='change-me' --tenant=local
```

Login: <http://localhost:8081/login>. Ganti password contoh. Command provisioning hanya berjalan pada environment `local`/`testing`.

Entrypoint memasang dependency Composer ketika volume `vendor` masih kosong. Service Node memasang dependency NPM lalu menjalankan Vite.

## Menyiapkan database lokal

MySQL init membuat:

- `sidbm_platform`
- `sidbm_shard_local`
- `sidbm_platform_test`
- `sidbm_shard_test`

Jalankan platform migration:

```bash
docker compose exec app php artisan migrate \
  --database=platform \
  --path=database/migrations/platform
```

Daftarkan shard lokal pada database platform. Nilai penting:

```text
code                 local
host                 mysql
port                 3306
database_name        sidbm_shard_local
credential_reference local
status               active
```

Lalu jalankan:

```bash
docker compose exec app php artisan tenancy:migrate-shards --shard=local --force
docker compose exec app php artisan tenancy:sync-registry --shard=local
```

`TENANCY_SHARD_CREDENTIALS_JSON` pada `.env` menyediakan kredensial reference `local` dan `test` untuk development saja.

## Frontend

```bash
docker compose logs -f node
# atau dari host:
npm install
npm run dev
npm run build
```

Entry frontend: `resources/js/app.js`. Halaman Inertia: `resources/js/Pages/`.

## Pengujian

Test standar melewati integration test MySQL kecuali flag diaktifkan:

```bash
docker compose exec app php artisan test
```

Integration test tenancy memakai database test disposable:

```bash
docker compose exec \
  -e RUN_TENANCY_INTEGRATION_TESTS=true \
  -e PLATFORM_DB_DATABASE=sidbm_platform_test \
  -e TENANT_DB_DATABASE=sidbm_shard_test \
  app php artisan test --configuration=phpunit.tenancy.xml.example
```

## Endpoint

- `/` — halaman Inertia/Vue.
- `/up` — health check Laravel.
- `/t/{tenant}/health` — health tenant; membutuhkan autentikasi dan membership aktif.

## Catatan produksi

Docker Compose ini untuk development. Jangan gunakan password contoh, bind mount, Vite dev server, atau credential JSON pada production. Backup cron server existing tetap menjadi mekanisme backup; aplikasi tidak menjadwalkan backup duplikat.

Dokumentasi arsitektur: [`docs/PROJECT_OVERVIEW.md`](docs/PROJECT_OVERVIEW.md). Struktur database: [`docs/DATABASE_STRUCTURE.md`](docs/DATABASE_STRUCTURE.md).

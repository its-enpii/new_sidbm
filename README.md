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
docker compose exec app php artisan migrate \
  --database=platform \
  --path=database/migrations/platform --force
docker compose exec app php artisan sidbm:bootstrap-local --password='change-me'
```

`sidbm:bootstrap-local` idempotent: daftarkan shard+tenant `local`, migrate shard, sync registry, import COA, buka fiscal periods, seed master data/loan products, provision user `dev`.

Aplikasi: <http://localhost:8080/login> (ubah `APP_PORT` jika perlu)  
Vite HMR: port `VITE_PORT` (default 5173; contoh env memakai 5174)  
Login dev: `dev` / password yang di-set di bootstrap.

MySQL host port default **3307** (`FORWARD_DB_PORT`) agar tidak bentrok Laragon MySQL di 3306. Di dalam Docker, app tetap ke `mysql:3306`.

Entrypoint memasang Composer bila `vendor` kosong. Service Node memasang NPM lalu Vite.

## Database lokal (manual)

MySQL init membuat: `sidbm_platform`, `sidbm_shard_local`, `sidbm_platform_test`, `sidbm_shard_test`.

Tanpa bootstrap, urutan manual:

```bash
docker compose exec app php artisan migrate --database=platform --path=database/migrations/platform --force
# daftarkan shard code=local → sidbm_shard_local, credential_reference=local
docker compose exec app php artisan tenancy:migrate-shards --shard=local --force
docker compose exec app php artisan tenancy:sync-registry --shard=local
docker compose exec app php artisan tenancy:import-legacy-chart-of-accounts local
docker compose exec app php artisan sidbm:provision-dev-user --password='change-me' --tenant=local
```

`TENANCY_SHARD_CREDENTIALS_JSON` di `.env` untuk credential reference `local`/`test` (dev only).

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

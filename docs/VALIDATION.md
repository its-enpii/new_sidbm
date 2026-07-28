# Validation Notes

## Pemeriksaan statis

- Seluruh file PHP harus lolos `php -l`.
- `composer.json` dan `package.json` harus valid.
- Foreign key tenant komposit tidak menggunakan `ON DELETE SET NULL` pada `tenant_id`.
- Nama constraint/index MySQL harus maksimal 64 karakter.
- Migration platform dan shard tetap berada pada path terpisah.
- Backup scheduler tidak diduplikasi di Laravel atau Docker.

## Verifikasi development

```bash
cp .env.example .env
docker compose config
docker compose up --build -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan about
docker compose exec app php artisan migrate --database=platform --path=database/migrations/platform
docker compose exec app php artisan tenancy:migrate-shards --shard=local --force
docker compose exec app php artisan tenancy:sync-registry --shard=local
docker compose exec app php artisan test
docker compose exec node npm run build
```

Daftarkan shard `local` pada platform sebelum menjalankan command shard. Jalankan migration dan integration test hanya pada database disposable atau hasil restore terpisah.

## Verifikasi wajib sebelum produksi

- Import data tenant nyata pada lingkungan rehearsal.
- Rekonsiliasi jumlah record, legacy ID, debit, kredit, saldo akun, dan saldo pinjaman.
- Jalankan perbandingan laporan lama dan baru.
- Uji tenant isolation, queue context, cache namespace, dan restore satu tenant dari shared shard.
- Ganti `ConfigShardCredentialProvider` dengan secret manager/credential store terenkripsi.
- Gunakan image production immutable, reverse proxy TLS, worker queue terpisah, serta kredensial unik.

Jangan menjalankan pipeline migrasi langsung terhadap satu-satunya salinan produksi.

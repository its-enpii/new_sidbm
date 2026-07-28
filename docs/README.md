# SIDBM Database Redesign Package

Paket ini terdiri dari tiga deliverable utama:

1. `PROJECT_OVERVIEW.md` — gambaran proyek, arsitektur, tahapan migrasi, risiko, dan acceptance criteria.
2. `DATABASE_STRUCTURE.md` — rancangan detail platform database dan tenant shard database, termasuk pemetaan legacy.
3. `laravel-boilerplate/` — overlay boilerplate untuk proyek Laravel 13 baru.

## Keputusan inti

- platform database + beberapa tenant shard;
- shared table menggunakan `tenant_id`;
- `row_id` hanya untuk internal;
- `id` lama dipertahankan untuk laporan;
- ID baru memakai sequence per tenant;
- composite foreign key mencegah relasi lintas tenant;
- accounting menggunakan journal entries dan journal lines;
- saldo bulanan dihitung ulang sebagai projection;
- sistem backup cron existing tetap digunakan.

Baca `laravel-boilerplate/VALIDATION.md` sebelum mencoba migration pada MySQL.

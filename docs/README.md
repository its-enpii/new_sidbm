# Dokumentasi Proyek SIDBM Next

Indeks dokumentasi lengkap untuk arsitektur, basis data, billing, modul supervisi kabupaten, asisten AI, dan migrasi data SIDBM Next:

1. [PERBANDINGAN_SIDBM_LEGACY_VS_NEXT.md](PERBANDINGAN_SIDBM_LEGACY_VS_NEXT.md) — Dokumen analisis komparatif menyeluruh antara SIDBM Legacy (/sidbm) vs SIDBM Next (/new_sidbm), mencakup alasan upgrade, perbedaan arsitektur, basis data, modul kabupaten, SaaS billing, AI RAG, hingga infrastruktur.
2. [PROJECT_OVERVIEW.md](PROJECT_OVERVIEW.md) — Gambaran umum proyek, prinsip arsitektur multi-tenant sharding, sasaran, risiko, dan kriteria penyelesaian (*definition of done*).
3. [DATABASE_STRUCTURE.md](DATABASE_STRUCTURE.md) — Struktur detail skema database platform dan shard multi-tenant, pemetaan tabel legacy, serta pembentukan identitas ganda (ow_id vs legacy id).
4. [BILLING_TRIPAY_AUTOMATION.md](BILLING_TRIPAY_AUTOMATION.md) — Spesifikasi integrasi Payment Gateway Tripay (QRIS & Virtual Accounts), automatisasi tagihan perpanjangan, dan middleware pembatasan tenant overdue.
5. [ASSISTANT_INTEGRATION.md](ASSISTANT_INTEGRATION.md) — Spesifikasi integrasi Asisten AI (enpii/assistant), pgvector store RAG, Ollama embedding server, dan komponen chat interaktif.
6. [FEATURE_ROADMAP.md](FEATURE_ROADMAP.md) — Status implementasi fitur harian, laporan akuntansi, inventaris, billing, modul kabupaten, dan changelog rilis.
7. [CUTOVER_RUNBOOK.md](CUTOVER_RUNBOOK.md) — Panduan teknis migrasi dan cutover data per tenant dari database legacy ke SIDBM Next.
8. [RBAC_MATRIX.md](RBAC_MATRIX.md) — Matriks hak akses (*Role-Based Access Control*) dan permission granular modul tenant & platform.

## Keputusan Arsitektur Inti

- **Topologi**: Platform Database + Multi-Tenant Shard Database (	enant_id column-based isolation).
- **Identitas**: ow_id sebagai PK internal teknis, id lama dipertahankan utuh untuk laporan & audit.
- **Akuntansi**: Double-entry journal (journal_entries & journal_lines) yang seimbang dan bersifat *immutable*.
- **Supervisi Kabupaten**: Dashboard & laporan keuangan konsolidasi real-time lintas kecamatan (Neraca, LR, BB, Arus Kas, CALK, PDF).
- **SaaS Billing**: Integrasi Tripay (Scan QRIS & 8 Virtual Account Bank) dengan scheduler auto-invoice & penangguhan otomatis tenant overdue.
- **Infrastruktur**: Stack Dockerized lengkap (PHP-FPM 8.4, Nginx 1.29, MySQL 8.4, Redis Cache/Session/Queue Worker, PostgreSQL pgvector 16, Ollama LLM).

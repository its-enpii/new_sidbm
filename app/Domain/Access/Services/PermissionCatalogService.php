<?php

declare(strict_types=1);

namespace App\Domain\Access\Services;

/**
 * Single source of truth for the tenant-facing permission catalog.
 *
 * Consumed by the tenant RBAC screen (`/access/roles`) and by the platform
 * superadmin screen (`/admin/tenants/{tenant}/roles`) so both always render
 * the exact same matrix.
 */
final class PermissionCatalogService
{
    /** Role codes that ship with the platform and can never be recreated by a custom role. */
    public const PROTECTED_CODES = ['admin', 'regency_supervisor', 'province_supervisor'];

    /**
     * @return list<array{category: string, label: string, icon: string, permissions: list<array{key: string, label: string, description: string}>}>
     */
    public function groups(): array
    {
        return [
            [
                'category' => 'master',
                'label' => 'Data Master',
                'icon' => 'folder_shared',
                'permissions' => [
                    ['key' => 'members.view', 'label' => 'Lihat Anggota', 'description' => 'Melihat daftar dan profil anggota'],
                    ['key' => 'members.manage', 'label' => 'Kelola Anggota', 'description' => 'Tambah, ubah, dan hapus data anggota'],
                    ['key' => 'groups.view', 'label' => 'Lihat Kelompok', 'description' => 'Melihat daftar kelompok pemanfaat'],
                    ['key' => 'groups.manage', 'label' => 'Kelola Kelompok', 'description' => 'Tambah, ubah, dan hapus data kelompok'],
                    ['key' => 'villages.view', 'label' => 'Lihat Desa', 'description' => 'Melihat daftar desa/wilayah'],
                    ['key' => 'villages.manage', 'label' => 'Kelola Desa', 'description' => 'Ubah data desa/wilayah'],
                    ['key' => 'institutions.view', 'label' => 'Lihat Lembaga', 'description' => 'Melihat daftar lembaga eksternal'],
                    ['key' => 'institutions.manage', 'label' => 'Kelola Lembaga', 'description' => 'Tambah, ubah, dan hapus lembaga'],
                ],
            ],
            [
                'category' => 'lending',
                'label' => 'Pinjaman (SIDBM)',
                'icon' => 'account_balance',
                'permissions' => [
                    ['key' => 'loans.view', 'label' => 'Lihat Pinjaman & Laporan', 'description' => 'Melihat tahapan perguliran dan laporan pinjaman'],
                    ['key' => 'loans.propose', 'label' => 'Input Proposal', 'description' => 'Mendaftarkan proposal pinjaman baru'],
                    ['key' => 'loans.verify', 'label' => 'Verifikasi Pinjaman', 'description' => 'Melakukan verifikasi berkas dan lapangan'],
                    ['key' => 'loans.approve', 'label' => 'Penetapan Alokasi', 'description' => 'Menetapkan persetujuan alokasi pinjaman'],
                    ['key' => 'loans.disburse', 'label' => 'Pencairan Pinjaman', 'description' => 'Mencatat pencairan dana pinjaman ke kelompok'],
                    ['key' => 'loans.manage', 'label' => 'Kelola & Reschedule', 'description' => 'Edit proposal, reschedule, dan penghapusan piutang'],
                ],
            ],
            [
                'category' => 'accounting',
                'label' => 'Akuntansi & Keuangan',
                'icon' => 'receipt_long',
                'permissions' => [
                    ['key' => 'journals.view', 'label' => 'Lihat Jurnal & Akun', 'description' => 'Melihat daftar jurnal dan bagan akun (COA)'],
                    ['key' => 'journals.create', 'label' => 'Input Jurnal Umum', 'description' => 'Membuat dan memposting jurnal umum/pembuka'],
                    ['key' => 'installments.record', 'label' => 'Catat Angsuran', 'description' => 'Mencatat pembayaran angsuran pinjaman'],
                    ['key' => 'assets.view', 'label' => 'Lihat Inventaris', 'description' => 'Melihat daftar inventaris barang dan aset'],
                    ['key' => 'assets.manage', 'label' => 'Kelola Inventaris', 'description' => 'Tambah, ubah, dan hapus aset inventaris'],
                    ['key' => 'period_close.view', 'label' => 'Lihat Tutup Buku', 'description' => 'Melihat status periode dan tutup buku'],
                    ['key' => 'period_close.manage', 'label' => 'Proses Tutup Buku', 'description' => 'Menutup buku bulanan, tahunan, dan alokasi surplus'],
                    ['key' => 'reports.view', 'label' => 'Lihat Laporan Keuangan', 'description' => 'Melihat dan cetak neraca, laba rugi, arus kas, dll.'],
                    ['key' => 'reports.manage', 'label' => 'Kelola CALK', 'description' => 'Mengisi catatan atas laporan keuangan (CALK)'],
                    ['key' => 'tax.view', 'label' => 'Taksiran Pajak', 'description' => 'Melihat perhitungan taksiran pajak'],
                ],
            ],
            [
                'category' => 'operations',
                'label' => 'Operasional & Sistem',
                'icon' => 'tune',
                'permissions' => [
                    ['key' => 'budgeting.view', 'label' => 'Lihat Anggaran (E-Budgeting)', 'description' => 'Melihat rencana anggaran operasional'],
                    ['key' => 'budgeting.manage', 'label' => 'Kelola Anggaran', 'description' => 'Menyusun dan mengesahkan anggaran'],
                    ['key' => 'messages.send', 'label' => 'Kirim Notifikasi WA', 'description' => 'Mengirim notifikasi tagihan WhatsApp'],
                    ['key' => 'billing.view', 'label' => 'Lihat Tagihan Layanan', 'description' => 'Melihat invoice langganan sistem SaaS'],
                    ['key' => 'billing.pay', 'label' => 'Bayar Tagihan Layanan', 'description' => 'Melakukan checkout pembayaran SaaS'],
                    ['key' => 'assistant.use', 'label' => 'Asisten AI Ariel', 'description' => 'Menggunakan asisten AI dan tool bantuannya'],
                    ['key' => 'village_user.access', 'label' => 'Akses Operator Desa', 'description' => 'Mode khusus terbatas untuk operator desa'],
                    ['key' => 'settings.manage', 'label' => 'Pengaturan Tenant', 'description' => 'Mengatur identitas lembaga, logo, dan sistem'],
                    ['key' => 'users.view', 'label' => 'Lihat Daftar Pengguna', 'description' => 'Melihat staf dan operator tenant'],
                    ['key' => 'users.manage', 'label' => 'Kelola Pengguna', 'description' => 'Tambah, ubah status, dan reset password staf'],
                    ['key' => 'roles.view', 'label' => 'Lihat Daftar Role', 'description' => 'Melihat daftar role dan hak akses'],
                    ['key' => 'roles.manage', 'label' => 'Kelola Role & Hak Akses', 'description' => 'Membuat dan menyesuaikan hak akses role'],
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function flat(): array
    {
        $keys = [];
        foreach ($this->groups() as $group) {
            foreach ($group['permissions'] as $permission) {
                $keys[] = $permission['key'];
            }
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    public function groupKeys(string $category): array
    {
        foreach ($this->groups() as $group) {
            if ($group['category'] === $category) {
                return array_column($group['permissions'], 'key');
            }
        }

        return [];
    }

    public function isKnown(string $key): bool
    {
        return in_array($key, $this->flat(), true);
    }

    public function isProtectedCode(string $code): bool
    {
        return in_array(strtolower($code), self::PROTECTED_CODES, true);
    }
}

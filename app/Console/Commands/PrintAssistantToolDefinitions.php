<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Dump tool registry payload for pasting into encompletion embed admin. */
final class PrintAssistantToolDefinitions extends Command
{
    protected $signature = 'sidbm:assistant-tools
        {--base= : SIDBM public base URL, default APP_URL}';

    protected $description = 'Print encompletion Kategori-B tool definitions for SIDBM.';

    public function handle(): int
    {
        $base = rtrim((string) ($this->option('base') ?: config('app.url')), '/');

        $tools = [
            [
                'name' => 'search_members',
                'description' => 'Cari anggota by nama/NIK/telepon. Opsional filter kelompok (group_query). Return items + match_count + needs_clarification (true jika bukan tepat 1).',
                'requires_confirmation' => false,
                'endpoint_url' => $base.'/api/assistant/tools/search_members',
                'json_schema' => [
                    'type' => 'object',
                    'required' => ['query'],
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Nama/NIK/telepon min 2 karakter'],
                        'group_query' => ['type' => 'string', 'description' => 'Filter nama/kode kelompok'],
                    ],
                ],
            ],
            [
                'name' => 'search_groups',
                'description' => 'Cari kelompok by nama atau kode. Return candidates; needs_clarification jika ≠1.',
                'requires_confirmation' => false,
                'endpoint_url' => $base.'/api/assistant/tools/search_groups',
                'json_schema' => [
                    'type' => 'object',
                    'required' => ['query'],
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Nama/kode kelompok min 2 karakter'],
                    ],
                ],
            ],
            [
                'name' => 'search_loans',
                'description' => 'Cari pinjaman by kelompok, anggota, atau nomor. Prefer status active/disbursed. Tiap item memuat next_installment (pokok/jasa sisa).',
                'requires_confirmation' => false,
                'endpoint_url' => $base.'/api/assistant/tools/search_loans',
                'json_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'group_query' => ['type' => 'string'],
                        'member_query' => ['type' => 'string'],
                        'loan_number' => ['type' => 'string'],
                        'loan_row_id' => ['type' => 'integer'],
                        'status' => ['type' => 'string', 'description' => 'Opsional filter status exact'],
                    ],
                ],
            ],
            [
                'name' => 'get_loan',
                'description' => 'Detail pinjaman + sisa pokok + next_installment + anggota kelompok.',
                'requires_confirmation' => false,
                'endpoint_url' => $base.'/api/assistant/tools/get_loan',
                'json_schema' => [
                    'type' => 'object',
                    'required' => ['loan_row_id'],
                    'properties' => [
                        'loan_row_id' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'name' => 'list_accounts',
                'description' => 'Daftar akun postable. Filter code_prefix, query nama (Bank Jateng, Kas Tunai), atau cash_only.',
                'requires_confirmation' => false,
                'endpoint_url' => $base.'/api/assistant/tools/list_accounts',
                'json_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'code_prefix' => ['type' => 'string'],
                        'query' => ['type' => 'string', 'description' => 'Cari di name/code'],
                        'cash_only' => ['type' => 'boolean', 'description' => 'Hanya 1.1.01.*'],
                    ],
                ],
            ],
            [
                'name' => 'list_due_billing',
                'description' => 'Daftar tagihan angsuran jatuh tempo pada tanggal.',
                'requires_confirmation' => false,
                'endpoint_url' => $base.'/api/assistant/tools/list_due_billing',
                'json_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'due_date' => ['type' => 'string', 'description' => 'Y-m-d, default hari ini'],
                    ],
                ],
            ],
            [
                'name' => 'search_journals',
                'description' => 'Cari jurnal posted (koreksi/duplikat/angsuran salah). Filter tgl, amount, type, account_query, group_query (nama di desc/loan), recent. Item angsuran memuat loan+split pokok/jasa. possible_duplicate_of jika fingerprint sama.',
                'requires_confirmation' => false,
                'endpoint_url' => $base.'/api/assistant/tools/search_journals',
                'json_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'journal_row_id' => ['type' => 'integer'],
                        'transaction_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD exact day (sets from+to)'],
                        'date_from' => ['type' => 'string'],
                        'date_to' => ['type' => 'string'],
                        'amount' => ['type' => 'number'],
                        'transaction_type' => ['type' => 'string'],
                        'source_type' => ['type' => 'string', 'description' => 'loan_installment untuk angsuran'],
                        'query' => ['type' => 'string', 'description' => 'Cari di description'],
                        'account_query' => ['type' => 'string', 'description' => 'Nama/kode akun di lines'],
                        'group_query' => ['type' => 'string', 'description' => 'Filter nama kelompok di deskripsi/loan'],
                        'wrong_group_query' => ['type' => 'string'],
                        'installments_only' => ['type' => 'boolean'],
                        'recent' => ['type' => 'boolean', 'description' => 'true = 2 hari terakhir'],
                        'created_by_user_id' => ['type' => 'integer'],
                        'exclude_reversed' => ['type' => 'boolean'],
                        'limit' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'name' => 'create_journal_entry',
                'description' => 'Rencana/post jurnal. Default PREVIEW (preview=true) — tampilkan plan ke user. Post hanya confirm=true. Beli=pembelian_inventaris. Setor bank=pemindahan_saldo. Ambiguitas akun → needs_clarification. Tanggal Y-m-d absolut.',
                'requires_confirmation' => true,
                'endpoint_url' => $base.'/api/assistant/tools/create_journal_entry',
                'json_schema' => [
                    'type' => 'object',
                    'required' => [
                        'transaction_date',
                        'amount',
                    ],
                    'properties' => [
                        'confirm' => [
                            'type' => 'boolean',
                            'description' => 'false/omit=preview saja; true=post setelah user setuju',
                        ],
                        'transaction_date' => [
                            'type' => 'string',
                            'description' => 'Wajib YYYY-MM-DD absolut. Jangan kirim "kemarin".',
                        ],
                        'transaction_type' => [
                            'type' => 'string',
                            'enum' => [
                                'aset_masuk',
                                'aset_keluar',
                                'pemindahan_saldo',
                                'pembelian_inventaris',
                                'penyusutan_inventaris',
                                'cadangan_kerugian_piutang',
                            ],
                            'description' => 'Beli=pembelian_inventaris; setor bank=pemindahan_saldo. Boleh dikosongkan — server infer dari teks.',
                        ],
                        'description' => ['type' => 'string'],
                        'reference' => ['type' => 'string'],
                        'amount' => ['type' => 'number', 'description' => 'Nominal (wajib)'],
                        'debit_account_row_id' => ['type' => 'integer'],
                        'credit_account_row_id' => ['type' => 'integer'],
                        'bank_account_query' => ['type' => 'string', 'description' => 'Nama bank tujuan setor, mis. Bank Jateng / Kas di Bank Ops'],
                        'cash_account_query' => ['type' => 'string', 'description' => 'Sumber kas, default Kas Tunai'],
                        'asset_name' => ['type' => 'string'],
                        'asset_quantity' => ['type' => 'integer'],
                        'asset_unit_cost' => ['type' => 'number'],
                        'asset_useful_life_months' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'name' => 'reverse_journal',
                'description' => 'Rencana/batalkan jurnal posted. Default PREVIEW; post dengan confirm=true. Salah bank: correct_bank_account_query. Salah angsuran kelompok: wrong_group+correct_group/loan + repost. Duplikat: reverse tanpa repost. Multi → needs_clarification.',
                'requires_confirmation' => true,
                'endpoint_url' => $base.'/api/assistant/tools/reverse_journal',
                'json_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'confirm' => ['type' => 'boolean', 'description' => 'omit=preview; true=eksekusi reverse'],
                        'journal_row_id' => ['type' => 'integer', 'description' => 'Target; opsional jika filter unik'],
                        'reversal_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, default hari ini'],
                        'reason' => ['type' => 'string'],
                        'transaction_date' => ['type' => 'string', 'description' => 'Filter cari jurnal salah'],
                        'amount' => ['type' => 'number'],
                        'wrong_account_query' => ['type' => 'string', 'description' => 'Akun yang salah (mis. Bank Ops)'],
                        'wrong_group_query' => ['type' => 'string', 'description' => 'Kelompok yang salah ter-input angsuran'],
                        'account_query' => ['type' => 'string'],
                        'query' => ['type' => 'string'],
                        'transaction_type' => ['type' => 'string'],
                        'recent' => ['type' => 'boolean'],
                        'repost' => ['type' => 'boolean', 'description' => 'true = post entri/angsuran pengganti setelah reverse'],
                        'repost_installment' => ['type' => 'boolean'],
                        'correct_group_query' => ['type' => 'string', 'description' => 'Kelompok yang benar untuk angsuran'],
                        'correct_loan_id' => ['type' => 'integer'],
                        'correct_loan_row_id' => ['type' => 'integer'],
                        'correct_member_query' => ['type' => 'string'],
                        'member_query' => ['type' => 'string'],
                        'correct_bank_account_query' => ['type' => 'string', 'description' => 'Akun bank yang benar (mis. SPP)'],
                        'correct_account_query' => ['type' => 'string'],
                        'correct_debit_account_row_id' => ['type' => 'integer'],
                        'correct_credit_account_row_id' => ['type' => 'integer'],
                        'correct_cash_account_query' => ['type' => 'string'],
                        'correct_amount' => ['type' => 'number'],
                        'correct_transaction_type' => ['type' => 'string'],
                        'correct_description' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'name' => 'record_installment',
                'description' => 'Rencana/catat angsuran. Default PREVIEW: pecahan pokok/jasa, sisa tagihan, kelebihan/kurang + options. Post hanya confirm=true. Kelebihan: allocation_choice=apply_excess_to_principal|cap_to_due|cancel. Ambiguitas → needs_clarification.',
                'requires_confirmation' => true,
                'endpoint_url' => $base.'/api/assistant/tools/record_installment',
                'json_schema' => [
                    'type' => 'object',
                    'required' => ['transaction_date'],
                    'properties' => [
                        'confirm' => ['type' => 'boolean', 'description' => 'omit=preview; true=post'],
                        'allocation_choice' => [
                            'type' => 'string',
                            'description' => 'apply_excess_to_principal | cap_to_due | cancel',
                        ],
                        'transaction_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'loan_id' => ['type' => 'integer', 'description' => 'loans.row_id — opsional jika ada group/member query'],
                        'loan_row_id' => ['type' => 'integer'],
                        'group_query' => ['type' => 'string'],
                        'member_query' => ['type' => 'string', 'description' => 'Nama penyetor'],
                        'total_amount' => ['type' => 'number', 'description' => 'Total bayar; dipecah pokok+jasa dari jadwal'],
                        'amount' => ['type' => 'number', 'description' => 'Alias total_amount'],
                        'installment_number' => ['type' => 'integer'],
                        'principal_amount' => ['type' => 'number'],
                        'interest_amount' => ['type' => 'number'],
                        'penalty_amount' => ['type' => 'number'],
                        'cash_account_row_id' => ['type' => 'integer'],
                        'cash_account_query' => ['type' => 'string', 'description' => 'Default Kas Tunai'],
                        'description' => ['type' => 'string'],
                        'reference' => ['type' => 'integer', 'description' => 'member_row_id penyetor'],
                        'member_row_id' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'name' => 'send_billing_notices',
                'description' => 'Rencana/kirim WA tagihan. Default PREVIEW; kirim dengan confirm=true.',
                'requires_confirmation' => true,
                'endpoint_url' => $base.'/api/assistant/tools/send_billing_notices',
                'json_schema' => [
                    'type' => 'object',
                    'required' => ['due_date', 'installment_row_ids'],
                    'properties' => [
                        'confirm' => ['type' => 'boolean'],
                        'due_date' => ['type' => 'string'],
                        'installment_row_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
        ];

        $this->line(json_encode($tools, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}

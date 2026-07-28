<?php

declare(strict_types=1);

/**
 * Permission catalog + default role packs.
 *
 * roles table only stores assignment (code). Permission matrix lives here —
 * no role_permissions table until multi-tenant custom roles are needed.
 *
 * Users with zero assigned roles keep full access (legacy). Once a user has
 * any role, only the union of that role's permissions applies.
 */
return [
    'permissions' => [
        'members.view',
        'members.manage',
        'groups.view',
        'groups.manage',
        'loans.view',
        'loans.propose',
        'loans.verify',
        'loans.approve',
        'loans.disburse',
        'loans.manage',
        'journals.view',
        'journals.create',
        'installments.record',
        'budgeting.view',
        'budgeting.manage',
        'messages.send',
        'settings.manage',
        'assistant.use',
    ],

    'roles' => [
        'admin' => [
            'name' => 'Administrator',
            'is_system' => true,
            'permissions' => ['*'],
        ],
        'kasir' => [
            'name' => 'Kasir / Teller',
            'is_system' => true,
            'permissions' => [
                'members.view',
                'groups.view',
                'loans.view',
                'journals.view',
                'journals.create',
                'installments.record',
                'messages.send',
                'assistant.use',
            ],
        ],
        'verifikator' => [
            'name' => 'Verifikator',
            'is_system' => true,
            'permissions' => [
                'members.view',
                'groups.view',
                'loans.view',
                'loans.verify',
                'journals.view',
                'assistant.use',
            ],
        ],
        'viewer' => [
            'name' => 'Viewer',
            'is_system' => true,
            'permissions' => [
                'members.view',
                'groups.view',
                'loans.view',
                'journals.view',
                'budgeting.view',
                'assistant.use',
            ],
        ],
    ],

    /**
     * Map HTTP FormRequest / assistant tool → required permission.
     * Missing key = no extra check beyond auth.
     */
    'request_map' => [
        \App\Http\Requests\Accounting\JournalEntryRequest::class => 'journals.create',
        \App\Http\Requests\Accounting\LoanInstallmentJournalRequest::class => 'installments.record',
        \App\Http\Requests\Lending\LoanRequest::class => 'loans.propose',
        \App\Http\Requests\Lending\LoanVerifyRequest::class => 'loans.verify',
        \App\Http\Requests\Lending\LoanApproveRequest::class => 'loans.approve',
        \App\Http\Requests\Lending\LoanDisburseRequest::class => 'loans.disburse',
        \App\Http\Requests\Lending\LoanUpdateRequest::class => 'loans.manage',
        \App\Http\Requests\Lending\LoanWriteOffRequest::class => 'loans.manage',
        \App\Http\Requests\Lending\LoanRescheduleRequest::class => 'loans.manage',
        \App\Http\Requests\MasterData\MemberRequest::class => 'members.manage',
        \App\Http\Requests\MasterData\QuickMemberRequest::class => 'members.manage',
        \App\Http\Requests\MasterData\GroupRequest::class => 'groups.manage',
        \App\Http\Requests\Budgeting\SaveBudgetMonthRequest::class => 'budgeting.manage',
        \App\Http\Requests\Settings\IdentityRequest::class => 'settings.manage',
        \App\Http\Requests\Settings\LendingSystemRequest::class => 'settings.manage',
        \App\Http\Requests\Settings\LogoUploadRequest::class => 'settings.manage',
        \App\Http\Requests\Settings\WhatsappRequest::class => 'settings.manage',
        \App\Http\Requests\Settings\SignaturesRequest::class => 'settings.manage',
    ],

    'tool_map' => [
        'search_members' => 'members.view',
        'search_groups' => 'groups.view',
        'search_loans' => 'loans.view',
        'get_loan' => 'loans.view',
        'list_accounts' => 'journals.view',
        'search_journals' => 'journals.view',
        'list_due_billing' => 'messages.send',
        'create_journal_entry' => 'journals.create',
        'reverse_journal' => 'journals.create',
        'record_installment' => 'installments.record',
        'send_billing_notices' => 'messages.send',
    ],
];

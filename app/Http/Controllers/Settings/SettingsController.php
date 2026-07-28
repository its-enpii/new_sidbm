<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Domain\Documents\Services\SignatureTemplateService;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Http\Requests\Settings\IdentityRequest;
use App\Http\Requests\Settings\LendingSystemRequest;
use App\Http\Requests\Settings\LogoUploadRequest;
use App\Http\Requests\Settings\SignaturesRequest;
use App\Http\Requests\Settings\WhatsappRequest;
use App\Services\TenantSettingService;
use App\Services\WhatsappGatewayService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class SettingsController
{
    public function index(
        TenantContext $context,
        TenantSettingService $settings,
        WhatsappGatewayService $gateway,
        SignatureTemplateService $signatures,
    ): Response {
        $profile = OrganizationProfile::query()->first() ?? new OrganizationProfile([
            'legal_name' => '',
            'timezone' => 'Asia/Jakarta',
        ]);

        $columns = ['row_id', 'id', 'code', 'name', 'default_interest_rate', 'default_term_months', 'is_active'];
        if (DB::connection('tenant')->getSchemaBuilder()->hasColumn('loan_products', 'rounding_method')) {
            $columns[] = 'rounding_method';
        }

        $products = DB::connection('tenant')
            ->table('loan_products')
            ->orderBy('code')
            ->get($columns)
            ->map(fn ($p) => [
                'row_id' => (int) $p->row_id,
                'id' => (int) $p->id,
                'code' => (string) $p->code,
                'name' => (string) $p->name,
                'default_interest_rate' => (float) $p->default_interest_rate,
                'default_term_months' => (int) $p->default_term_months,
                'rounding_method' => (string) ($p->rounding_method ?? 'decimal_2'),
                'is_active' => (bool) $p->is_active,
            ])
            ->values()
            ->all();

        $logoUrl = $profile->logo_path ? asset('storage/'.$profile->logo_path) : null;
        $templates = $signatures->all();
        $hasAny = collect($templates)->contains(fn (string $html) => trim($html) !== '');
        if (! $hasAny) {
            $templates['default'] = SignatureTemplateService::starterHtml();
        }

        return Inertia::render('Settings/Index', [
            'identity' => [
                'legal_name' => (string) $profile->legal_name,
                'short_name' => $profile->short_name,
                'registration_number' => $profile->registration_number,
                'tax_number' => $profile->tax_number,
                'address' => $profile->address,
                'phone' => $profile->phone,
                'email' => $profile->email,
                'website' => $profile->website,
                'timezone' => (string) ($profile->timezone ?: 'Asia/Jakarta'),
                'operational_start_date' => $profile->operational_start_date?->toDateString(),
            ],
            'products' => $products,
            'logoUrl' => $logoUrl,
            'whatsapp' => $this->whatsappPayload($settings, $gateway),
            'signatures' => [
                'templates' => $templates,
                'reportTypes' => $signatures->reportTypes(),
            ],
        ]);
    }

    public function updateIdentity(IdentityRequest $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validated();
        $tenantId = $context->id();

        DB::connection('tenant')->table('organization_profiles')->updateOrInsert(
            ['tenant_id' => $tenantId],
            [
                'legal_name' => $data['legal_name'],
                'short_name' => $data['short_name'] ?? null,
                'registration_number' => $data['registration_number'] ?? null,
                'tax_number' => $data['tax_number'] ?? null,
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'website' => $data['website'] ?? null,
                'timezone' => $data['timezone'],
                'operational_start_date' => isset($data['operational_start_date'])
                    ? CarbonImmutable::parse($data['operational_start_date'])->toDateString()
                    : null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return $this->flashRedirect('Identitas lembaga berhasil diperbarui.', 'identity');
    }

    public function updateLendingSystem(LendingSystemRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $now = now();
        $hasRounding = DB::connection('tenant')->getSchemaBuilder()->hasColumn('loan_products', 'rounding_method');

        foreach ($data['products'] as $row) {
            $update = [
                'default_interest_rate' => (float) $row['default_interest_rate'],
                'default_term_months' => (int) $row['default_term_months'],
                'updated_at' => $now,
            ];
            if ($hasRounding) {
                $update['rounding_method'] = $row['rounding_method'];
            }
            DB::connection('tenant')->table('loan_products')
                ->where('row_id', $row['row_id'])
                ->update($update);
        }

        return $this->flashRedirect('Pengaturan sistem pinjaman berhasil diperbarui.', 'lending-system');
    }

    public function updateLogo(LogoUploadRequest $request, TenantContext $context): RedirectResponse
    {
        $tenantId = $context->id();
        $file = $request->file('logo');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');

        $profile = OrganizationProfile::query()->first();
        if ($profile !== null && $profile->logo_path) {
            Storage::disk('public')->delete($profile->logo_path);
        }

        $path = $file->storeAs("tenants/{$tenantId}", 'logo.'.$extension, 'public');

        DB::connection('tenant')->table('organization_profiles')->updateOrInsert(
            ['tenant_id' => $tenantId],
            [
                'legal_name' => $profile->legal_name ?? '',
                'timezone' => $profile->timezone ?? 'Asia/Jakarta',
                'logo_path' => $path,
                'updated_at' => now(),
                'created_at' => $profile->created_at ?? now(),
            ]
        );

        return $this->flashRedirect('Logo berhasil diperbarui.', 'logo');
    }

    public function destroyLogo(TenantContext $context): RedirectResponse
    {
        $tenantId = $context->id();
        $profile = OrganizationProfile::query()->first();

        if ($profile !== null && $profile->logo_path) {
            Storage::disk('public')->delete($profile->logo_path);
            DB::connection('tenant')->table('organization_profiles')
                ->where('tenant_id', $tenantId)
                ->update(['logo_path' => null, 'updated_at' => now()]);
        }

        return $this->flashRedirect('Logo berhasil dihapus.', 'logo');
    }

    public function updateWhatsapp(WhatsappRequest $request, TenantSettingService $settings, WhatsappGatewayService $gateway): RedirectResponse
    {
        $data = $request->validated();

        if (array_key_exists('pairing_phone', $data) && is_string($data['pairing_phone'])) {
            $gateway->setPairingPhone($data['pairing_phone']);
        }

        $settings->set('whatsapp.template_billing', $data['template_billing'] ?? '');
        $settings->set('whatsapp.template_installment', $data['template_installment'] ?? '');
        $gateway->setEnabled((bool) ($data['is_enabled'] ?? false));

        return $this->flashRedirect('Pengaturan WhatsApp berhasil disimpan.', 'whatsapp');
    }

    public function updateSignatures(SignaturesRequest $request, SignatureTemplateService $signatures): RedirectResponse
    {
        $data = $request->validated();
        $signatures->save($data['templates'] ?? []);

        return $this->flashRedirect('Template tanda tangan berhasil disimpan.', 'signatures');
    }

    public function testWhatsapp(WhatsappGatewayService $gateway): JsonResponse
    {
        return response()->json($gateway->connectionState());
    }

    public function pairWhatsapp(Request $request, WhatsappGatewayService $gateway): JsonResponse
    {
        $validated = $request->validate([
            'pairing_phone' => ['required', 'string', 'max:20'],
        ]);

        return response()->json($gateway->pairWithPhone($validated['pairing_phone']));
    }

    /**
     * @return array<string, mixed>
     */
    private function whatsappPayload(TenantSettingService $settings, WhatsappGatewayService $gateway): array
    {
        $state = $gateway->isConfigured()
            ? $gateway->connectionState()
            : [
                'success' => false,
                'status' => 'unconfigured',
                'message' => 'EVOLUTION_URL / EVOLUTION_API_KEY belum diisi di environment server.',
                'state' => null,
                'instance' => $gateway->getInstance(),
            ];

        return [
            'pairing_phone' => $gateway->getPairingPhone(),
            'instance' => $gateway->getInstance(),
            'configured' => $gateway->isConfigured(),
            'template_billing' => (string) ($settings->get('whatsapp.template_billing', '') ?: ''),
            'template_installment' => (string) ($settings->get('whatsapp.template_installment', '') ?: ''),
            'is_enabled' => $gateway->isEnabled(),
            'connection' => [
                'status' => $state['status'] ?? 'unknown',
                'state' => $state['state'] ?? null,
                'message' => $state['message'] ?? null,
            ],
        ];
    }

    private function flashRedirect(string $message, string $tab): RedirectResponse
    {
        session()->flash('success', ['message' => $message, 'tab' => $tab]);

        return redirect()->route('settings.index', ['tab' => $tab]);
    }
}
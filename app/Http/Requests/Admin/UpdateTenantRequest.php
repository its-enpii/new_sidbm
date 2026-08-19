<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Platform\Tenant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_superadmin === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'district_code' => ['nullable', 'string', 'regex:/^\d{6}$/'],
            'status' => ['required', Rule::in(['active', 'suspended', 'provisioning', 'provisioning_failed'])],
            'timezone' => ['nullable', 'string', 'max:50'],
            'custom_domains' => ['nullable', 'array'],
            'custom_domains.*' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Tenant|null $tenant */
            $tenant = $this->route('tenant');
            $tenantRowId = $tenant instanceof Tenant ? (int) $tenant->row_id : null;

            $domains = (array) $this->input('custom_domains', []);
            $reserved = ['localhost', '127.0.0.1', '::1', 'host.docker.internal'];
            $normalizedList = [];

            foreach ($domains as $index => $rawDomain) {
                if (! is_string($rawDomain) || trim($rawDomain) === '') {
                    continue;
                }

                $clean = strtolower(trim($rawDomain));
                // Hapus protocol dan trailing slash jika ada
                $clean = preg_replace('#^https?://#', '', $clean);
                $clean = trim((string) explode('/', $clean)[0]);
                $clean = trim((string) explode(':', $clean)[0]);

                if (in_array($clean, $reserved, true)) {
                    $validator->errors()->add("custom_domains.{$index}", "Domain [{$clean}] adalah reserved hostname sistem.");

                    continue;
                }

                if (! filter_var($clean, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                    $validator->errors()->add("custom_domains.{$index}", "Format domain [{$clean}] tidak valid.");

                    continue;
                }

                if (in_array($clean, $normalizedList, true)) {
                    $validator->errors()->add("custom_domains.{$index}", "Domain [{$clean}] dimasukkan lebih dari satu kali.");

                    continue;
                }

                $normalizedList[] = $clean;
            }

            if ($validator->errors()->isNotEmpty() || $normalizedList === []) {
                return;
            }

            // Validasi keunikan domain terhadap tenant lain
            $otherTenants = Tenant::query()
                ->when($tenantRowId !== null, fn ($q) => $q->where('row_id', '!=', $tenantRowId))
                ->whereNotNull('metadata')
                ->get(['row_id', 'name', 'code', 'metadata']);

            foreach ($otherTenants as $other) {
                $meta = is_array($other->metadata) ? $other->metadata : [];
                $otherDomains = $meta['domains'] ?? ($meta['domain'] ?? []);
                $otherDomains = is_array($otherDomains) ? $otherDomains : [$otherDomains];

                foreach ($otherDomains as $od) {
                    $odClean = strtolower(trim((string) $od));
                    if (in_array($odClean, $normalizedList, true)) {
                        $validator->errors()->add(
                            'custom_domains',
                            "Domain [{$odClean}] telah digunakan oleh tenant [{$other->name} ({$other->code})]."
                        );
                        break 2;
                    }
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama tenant',
            'status' => 'status',
            'timezone' => 'zona waktu',
            'custom_domains' => 'custom domain',
        ];
    }
}

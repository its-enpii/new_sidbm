<?php

declare(strict_types=1);

namespace App\Domain\Membership\Models;

use App\Models\Tenant\TenantModel;
use Carbon\CarbonImmutable;

final class OrganizationProfile extends TenantModel
{
    protected $table = 'organization_profiles';

    protected function casts(): array
    {
        return [
            'operational_start_date' => 'immutable_date',
        ];
    }

    public function getLogoUrlAttribute(): ?string
    {
        $path = $this->logo_path;
        if (! is_string($path) || $path === '') {
            return null;
        }

        return asset('storage/'.ltrim($path, '/'));
    }

    public function displayName(): string
    {
        return $this->short_name ?: $this->legal_name;
    }

    public function operationalStartDate(): ?CarbonImmutable
    {
        return $this->operational_start_date;
    }
}

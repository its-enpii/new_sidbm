<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

final class User extends Authenticatable
{
    use Notifiable;

    protected $connection = 'platform';

    protected $primaryKey = 'row_id';

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_superadmin' => 'boolean',
            'is_regency_user' => 'boolean',
            'birth_date' => 'date',
            'appointed_at' => 'date',
        ];
    }

    public function isRegencyUser(): bool
    {
        return $this->is_regency_user === true;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'row_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, 'user_id', 'row_id');
    }
}

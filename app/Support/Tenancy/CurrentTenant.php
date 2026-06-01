<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use App\Models\User;

class CurrentTenant
{
    protected ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    public function activate(Tenant $tenant): void
    {
        session([config('tenancy.session_key') => $tenant->getKey()]);

        $this->tenant = $tenant;
    }

    public function clear(): void
    {
        session()->forget(config('tenancy.session_key'));

        $this->tenant = null;
    }

    public function resolveForUser(?User $user): ?Tenant
    {
        if (! $user) {
            return null;
        }

        if (! $user->isSuperuser()) {
            return $user->tenant;
        }

        $tenantId = session(config('tenancy.session_key'));

        if (! $tenantId) {
            return null;
        }

        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            $this->clear();
        }

        return $tenant;
    }
}

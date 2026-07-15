<?php

namespace App\Support\Contracts;

use App\Models\Contract;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantConnectionManager;
use Illuminate\Support\Facades\DB;

class ContractNumberService
{
    public function nextForTenant(?Tenant $tenant): string
    {
        $currentTenant = app(CurrentTenant::class);
        $resolvedTenant = $tenant ?? $currentTenant->get();

        if (! $resolvedTenant) {
            return '1';
        }

        $previousTenant = $currentTenant->get();
        $shouldRestoreTenant = ! $previousTenant || (int) $previousTenant->getKey() !== (int) $resolvedTenant->getKey();

        if ($shouldRestoreTenant) {
            app(TenantConnectionManager::class)->activate($resolvedTenant);
            $currentTenant->set($resolvedTenant);
        }

        try {
            $maxNumber = Contract::query()
                ->where('tenant_id', $resolvedTenant->getKey())
                ->pluck('contract_number')
                ->map(fn (mixed $contractNumber): string => trim((string) $contractNumber))
                ->filter(fn (string $contractNumber): bool => preg_match('/^\d+$/', $contractNumber) === 1)
                ->map(fn (string $contractNumber): int => (int) $contractNumber)
                ->max() ?? 0;

            return (string) ($maxNumber + 1);
        } finally {
            if ($shouldRestoreTenant) {
                $currentTenant->set($previousTenant);
                app(TenantConnectionManager::class)->activate($previousTenant);
                DB::purge(config('tenancy.database_connection'));
            }
        }
    }
}

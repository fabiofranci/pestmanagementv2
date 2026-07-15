<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;

class TenantModules
{
    public const DASHBOARD = 'dashboard';

    public const CUSTOMERS = 'customers';

    public const CUSTOMER_GROUPS = 'customer_groups';

    public const CUSTOMER_SITES = 'customer_sites';

    public const CONTRACTS = 'contracts';

    public const AREAS = 'areas';

    public const MONITORING_POINTS = 'monitoring_points';

    public const SERVICE_TYPES = 'service_types';

    public const PEST_TYPES = 'pest_types';

    public const ORGANIZATIONS = 'organizations';

    public static function options(): array
    {
        return [
            self::DASHBOARD => 'Dashboard',
            self::CUSTOMERS => 'Clienti',
            self::CUSTOMER_GROUPS => 'Gruppi clienti',
            self::CUSTOMER_SITES => 'Sedi cliente',
            self::CONTRACTS => 'Contratti',
            self::AREAS => 'Aree',
            self::MONITORING_POINTS => 'Punti di monitoraggio',
            self::SERVICE_TYPES => 'Tipi di servizio',
            self::PEST_TYPES => 'Tipi di infestante',
            self::ORGANIZATIONS => 'Organizzazioni',
        ];
    }

    public static function standardOrder(): array
    {
        return [
            self::DASHBOARD,
            self::CUSTOMERS,
            self::CUSTOMER_GROUPS,
            self::CUSTOMER_SITES,
            self::CONTRACTS,
            self::AREAS,
            self::MONITORING_POINTS,
            self::SERVICE_TYPES,
            self::PEST_TYPES,
            self::ORGANIZATIONS,
        ];
    }

    public function currentTenantHas(?string $module): bool
    {
        if ($module === null || $module === '') {
            return true;
        }

        $tenant = app(CurrentTenant::class)->get();

        if (! $tenant) {
            return true;
        }

        return $tenant->hasModuleEnabled($module);
    }

    public function tenantHas(?Tenant $tenant, ?string $module): bool
    {
        if ($module === null || $module === '') {
            return true;
        }

        if (! $tenant) {
            return true;
        }

        return $tenant->hasModuleEnabled($module);
    }

    public function currentTenantSort(?string $module, ?int $defaultSort = null): ?int
    {
        return $this->tenantSort(app(CurrentTenant::class)->get(), $module, $defaultSort);
    }

    public function tenantSort(?Tenant $tenant, ?string $module, ?int $defaultSort = null): ?int
    {
        if ($module === null || $module === '') {
            return $defaultSort;
        }

        if (! $tenant) {
            return $defaultSort;
        }

        $moduleOrder = $this->normalizeModuleList($tenant->module_order);

        if ($moduleOrder === []) {
            return $defaultSort;
        }

        $configuredSort = $tenant->getModuleSort($module);

        if ($configuredSort !== null) {
            return $configuredSort;
        }

        $standardPosition = array_search($module, self::standardOrder(), true);

        return ((count($moduleOrder) + 1) * 1000)
            + ($standardPosition === false ? 900 : (($standardPosition + 1) * 10));
    }

    /**
     * @return array<int, string>
     */
    public function normalizeModuleList(mixed $modules): array
    {
        if (! is_array($modules)) {
            return [];
        }

        return collect($modules)
            ->filter(fn (mixed $module): bool => is_string($module) && array_key_exists($module, self::options()))
            ->unique()
            ->values()
            ->all();
    }
}

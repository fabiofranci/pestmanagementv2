<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;

class TenantModules
{
    public const DASHBOARD = 'dashboard';

    public const CUSTOMERS = 'customers';

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
            self::CUSTOMER_SITES => 'Sedi cliente',
            self::CONTRACTS => 'Contratti',
            self::AREAS => 'Aree',
            self::MONITORING_POINTS => 'Punti di monitoraggio',
            self::SERVICE_TYPES => 'Tipi di servizio',
            self::PEST_TYPES => 'Tipi di infestante',
            self::ORGANIZATIONS => 'Organizzazioni',
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
}

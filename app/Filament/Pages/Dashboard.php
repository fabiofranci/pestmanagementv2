<?php

namespace App\Filament\Pages;

use App\Support\Tenancy\TenantModules;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public static function shouldRegisterNavigation(): bool
    {
        return parent::shouldRegisterNavigation()
            && app(TenantModules::class)->currentTenantHas(TenantModules::DASHBOARD);
    }

    public static function canAccess(): bool
    {
        return parent::canAccess()
            && app(TenantModules::class)->currentTenantHas(TenantModules::DASHBOARD);
    }
}

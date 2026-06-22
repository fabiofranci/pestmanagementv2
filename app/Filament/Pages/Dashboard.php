<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BillingSchedulesDueWidget;
use App\Filament\Widgets\ContractsExpiringWidget;
use App\Filament\Widgets\ScheduledInterventionsDueWidget;
use App\Support\Tenancy\TenantModules;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public static function getNavigationSort(): ?int
    {
        return app(TenantModules::class)->currentTenantSort(
            TenantModules::DASHBOARD,
            parent::getNavigationSort(),
        );
    }

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

    public function getWidgets(): array
    {
        return [
            ContractsExpiringWidget::class,
            BillingSchedulesDueWidget::class,
            ScheduledInterventionsDueWidget::class,
        ];
    }
}

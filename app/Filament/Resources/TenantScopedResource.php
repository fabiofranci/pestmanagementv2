<?php

namespace App\Filament\Resources;

use App\Models\Area;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\MonitoringPoint;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantModules;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class TenantScopedResource extends Resource
{
    protected static bool $allowsCustomerUsers = false;

    protected static ?string $tenantModule = null;

    public static function shouldRegisterNavigation(): bool
    {
        return parent::shouldRegisterNavigation() && static::canAccess();
    }

    public static function getNavigationSort(): ?int
    {
        return app(TenantModules::class)->currentTenantSort(
            static::$tenantModule,
            parent::getNavigationSort(),
        );
    }

    public static function canAccess(): bool
    {
        if (! app(CurrentTenant::class)->hasTenant()) {
            return false;
        }

        if (! static::isTenantModuleEnabled()) {
            return false;
        }

        $user = static::currentUser();

        if (! $user) {
            return false;
        }

        if ($user->isTenantCustomer()) {
            return static::$allowsCustomerUsers;
        }

        return true;
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::canAccess() && ! static::isCustomerPortalUser();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canView($record) && ! static::isCustomerPortalUser();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canView($record) && ! static::isCustomerPortalUser();
    }

    public static function canDeleteAny(): bool
    {
        return static::canAccess() && ! static::isCustomerPortalUser();
    }

    public static function canView(Model $record): bool
    {
        if (! static::canAccess()) {
            return false;
        }

        if (! static::isCustomerPortalUser()) {
            return true;
        }

        return static::scopeCustomerQuery(static::getModel()::query())
            ->whereKey($record->getKey())
            ->exists();
    }

    public static function getEloquentQuery(): Builder
    {
        return static::scopeCustomerQuery(parent::getEloquentQuery());
    }

    protected static function scopeCustomerQuery(Builder $query): Builder
    {
        $user = static::currentUser();

        if (! $user?->isTenantCustomer()) {
            return $query;
        }

        $customerId = $user->customer_id;

        if (! $customerId) {
            return $query->whereRaw('1 = 0');
        }

        return match (static::getModel()) {
            Customer::class => $query->whereKey($customerId),
            CustomerSite::class, Contract::class => $query->where('customer_id', $customerId),
            Area::class => $query->whereHas('site', fn (Builder $siteQuery): Builder => $siteQuery->where('customer_id', $customerId)),
            MonitoringPoint::class => $query->whereHas('area.site', fn (Builder $siteQuery): Builder => $siteQuery->where('customer_id', $customerId)),
            default => $query->whereRaw('1 = 0'),
        };
    }

    protected static function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    protected static function isCustomerPortalUser(): bool
    {
        return static::currentUser()?->isTenantCustomer() ?? false;
    }

    protected static function isTenantModuleEnabled(): bool
    {
        return app(TenantModules::class)->currentTenantHas(static::$tenantModule);
    }
}

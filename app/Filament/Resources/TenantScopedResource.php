<?php

namespace App\Filament\Resources;

use App\Support\Tenancy\CurrentTenant;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

abstract class TenantScopedResource extends Resource
{
    public static function shouldRegisterNavigation(): bool
    {
        return parent::shouldRegisterNavigation() && app(CurrentTenant::class)->hasTenant();
    }

    public static function canAccess(): bool
    {
        return app(CurrentTenant::class)->hasTenant();
    }

    public static function canViewAny(): bool
    {
        return app(CurrentTenant::class)->hasTenant();
    }

    public static function canCreate(): bool
    {
        return app(CurrentTenant::class)->hasTenant();
    }

    public static function canEdit(Model $record): bool
    {
        return app(CurrentTenant::class)->hasTenant();
    }

    public static function canDelete(Model $record): bool
    {
        return app(CurrentTenant::class)->hasTenant();
    }

    public static function canDeleteAny(): bool
    {
        return app(CurrentTenant::class)->hasTenant();
    }

    public static function canView(Model $record): bool
    {
        return app(CurrentTenant::class)->hasTenant();
    }
}

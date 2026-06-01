<?php

namespace App\Filament\Actions;

use Closure;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;

class TenantAdminActionGroup
{
    public static function make(Closure $resolveTenant): ActionGroup
    {
        return ActionGroup::make([
            ViewTenantAdminAction::make($resolveTenant),
            EditTenantAdminAction::make($resolveTenant),
            DeleteTenantAdminAction::make($resolveTenant),
            CreateTenantAdminAction::make($resolveTenant),
        ])
            ->label('Utente tenant')
            ->icon(Heroicon::OutlinedUserCircle)
            ->color('gray')
            ->button();
    }
}

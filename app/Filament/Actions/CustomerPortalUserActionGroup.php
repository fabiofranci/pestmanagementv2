<?php

namespace App\Filament\Actions;

use App\Models\User;
use Closure;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;

class CustomerPortalUserActionGroup
{
    public static function make(Closure $resolveCustomer): ActionGroup
    {
        return ActionGroup::make([
            ViewCustomerPortalUserAction::make($resolveCustomer),
            EditCustomerPortalUserAction::make($resolveCustomer),
            DeleteCustomerPortalUserAction::make($resolveCustomer),
            CreateCustomerPortalUserAction::make($resolveCustomer),
        ])
            ->label('Accesso cliente')
            ->icon(Heroicon::OutlinedUserCircle)
            ->color('gray')
            ->hidden(fn (): bool => auth()->user() instanceof User && auth()->user()->isTenantCustomer())
            ->button();
    }
}

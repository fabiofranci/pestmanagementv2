<?php

namespace App\Filament\Actions;

use App\Models\Customer;
use App\Support\Tenancy\CustomerPortalUserManager;
use Closure;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Icons\Heroicon;

class ViewCustomerPortalUserAction
{
    public static function make(Closure $resolveCustomer): Action
    {
        return Action::make('viewCustomerPortalUser')
            ->label('Vedi accesso')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->hidden(function (?Customer $record = null) use ($resolveCustomer): bool {
                $customer = $resolveCustomer($record);

                return ! $customer instanceof Customer
                    || app(CustomerPortalUserManager::class)->getUser($customer) === null;
            })
            ->infolist([
                TextEntry::make('name')
                    ->label('Nome')
                    ->state(function (?Customer $record = null) use ($resolveCustomer): ?string {
                        $customer = $resolveCustomer($record);

                        return $customer instanceof Customer
                            ? app(CustomerPortalUserManager::class)->getUser($customer)?->name
                            : null;
                    }),
                TextEntry::make('email')
                    ->label('Email')
                    ->state(function (?Customer $record = null) use ($resolveCustomer): ?string {
                        $customer = $resolveCustomer($record);

                        return $customer instanceof Customer
                            ? app(CustomerPortalUserManager::class)->getUser($customer)?->email
                            : null;
                    }),
            ])
            ->modalHeading('Accesso area riservata')
            ->modalSubmitAction(false);
    }
}

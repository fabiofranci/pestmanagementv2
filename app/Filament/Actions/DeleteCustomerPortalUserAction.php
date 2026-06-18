<?php

namespace App\Filament\Actions;

use App\Models\Customer;
use App\Models\User;
use App\Support\Tenancy\CustomerPortalUserManager;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Throwable;

class DeleteCustomerPortalUserAction
{
    public static function make(Closure $resolveCustomer): Action
    {
        return Action::make('deleteCustomerPortalUser')
            ->label('Elimina accesso')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->hidden(function (?Customer $record = null) use ($resolveCustomer): bool {
                $customer = $resolveCustomer($record);

                return ! $customer instanceof Customer
                    || app(CustomerPortalUserManager::class)->getUser($customer) === null;
            })
            ->modalHeading('Elimina accesso area riservata')
            ->modalDescription('Il cliente non potra piu entrare nell area riservata con queste credenziali.')
            ->modalSubmitActionLabel('Elimina accesso')
            ->action(function (Action $action, ?Customer $record = null) use ($resolveCustomer): void {
                $customer = $resolveCustomer($record);

                abort_unless($customer instanceof Customer, 404);

                $user = app(CustomerPortalUserManager::class)->getUser($customer);

                abort_unless($user instanceof User, 404);

                try {
                    app(CustomerPortalUserManager::class)->deleteUser($customer, $user);
                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Eliminazione accesso fallita')
                        ->body($exception->getMessage())
                        ->send();

                    $action->halt();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Accesso cliente eliminato')
                    ->body("Credenziali rimosse per il cliente {$customer->name}.")
                    ->send();
            });
    }
}

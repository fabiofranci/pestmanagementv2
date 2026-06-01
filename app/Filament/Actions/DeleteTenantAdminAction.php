<?php

namespace App\Filament\Actions;

use App\Models\Tenant;
use App\Support\Tenancy\TenantAdminManager;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Throwable;

class DeleteTenantAdminAction
{
    public static function make(Closure $resolveTenant): Action
    {
        return Action::make('deleteTenantAdmin')
            ->label('Elimina admin tenant')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->hidden(function (?Tenant $record = null) use ($resolveTenant): bool {
                $tenant = $resolveTenant($record);

                return ! $tenant instanceof Tenant
                    || app(TenantAdminManager::class)->getAdmin($tenant) === null;
            })
            ->requiresConfirmation()
            ->modalHeading('Elimina admin tenant')
            ->modalDescription(function (?Tenant $record = null) use ($resolveTenant): string {
                $tenant = $resolveTenant($record);
                $user = $tenant instanceof Tenant ? app(TenantAdminManager::class)->getAdmin($tenant) : null;

                return $user
                    ? "Stai per eliminare l utente {$user->email} collegato al tenant {$tenant->name}."
                    : 'L utente collegato a questo tenant non e disponibile.';
            })
            ->modalSubmitActionLabel('Elimina accesso')
            ->action(function (Action $action, ?Tenant $record = null) use ($resolveTenant): void {
                $tenant = $resolveTenant($record);

                abort_unless($tenant instanceof Tenant, 404);

                $user = app(TenantAdminManager::class)->getAdmin($tenant);

                if (! $user) {
                    Notification::make()
                        ->danger()
                        ->title('Nessun admin tenant')
                        ->body('Non esiste un utente collegato da eliminare.')
                        ->send();

                    $action->halt();

                    return;
                }

                $email = $user->email;

                try {
                    app(TenantAdminManager::class)->deleteAdmin($tenant, $user);
                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Eliminazione admin fallita')
                        ->body($exception->getMessage())
                        ->send();

                    $action->halt();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Admin tenant eliminato')
                    ->body("Utente {$email} eliminato con successo.")
                    ->send();
            });
    }
}

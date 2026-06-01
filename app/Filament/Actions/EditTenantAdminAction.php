<?php

namespace App\Filament\Actions;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantAdminManager;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;
use Throwable;

class EditTenantAdminAction
{
    public static function make(Closure $resolveTenant): Action
    {
        return Action::make('editTenantAdmin')
            ->label('Modifica admin tenant')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('primary')
            ->hidden(function (?Tenant $record = null) use ($resolveTenant): bool {
                $tenant = $resolveTenant($record);

                return ! $tenant instanceof Tenant
                    || app(TenantAdminManager::class)->getAdmin($tenant) === null;
            })
            ->schema([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->autocomplete(false)
                    ->rule(function (?Tenant $record = null) use ($resolveTenant) {
                        $tenant = $resolveTenant($record);
                        $user = $tenant instanceof Tenant ? app(TenantAdminManager::class)->getAdmin($tenant) : null;

                        return Rule::unique(User::class, 'email')->ignore($user);
                    }),
                TextInput::make('password')
                    ->label('Nuova password')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->confirmed()
                    ->autocomplete('new-password')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Lasciala vuota per mantenere la password attuale.'),
                TextInput::make('password_confirmation')
                    ->label('Conferma nuova password')
                    ->password()
                    ->revealable()
                    ->same('password')
                    ->autocomplete('new-password')
                    ->dehydrated(false),
            ])
            ->fillForm(function (?Tenant $record = null) use ($resolveTenant): array {
                $tenant = $resolveTenant($record);
                $user = $tenant instanceof Tenant ? app(TenantAdminManager::class)->getAdmin($tenant) : null;

                return [
                    'name' => $user?->name,
                    'email' => $user?->email,
                ];
            })
            ->modalHeading('Modifica admin tenant')
            ->modalSubmitActionLabel('Salva accesso')
            ->action(function (array $data, Action $action, ?Tenant $record = null) use ($resolveTenant): void {
                $tenant = $resolveTenant($record);

                abort_unless($tenant instanceof Tenant, 404);

                $user = app(TenantAdminManager::class)->getAdmin($tenant);

                if (! $user) {
                    Notification::make()
                        ->danger()
                        ->title('Nessun admin tenant')
                        ->body('Crea prima un utente collegato a questo tenant.')
                        ->send();

                    $action->halt();

                    return;
                }

                try {
                    $user = app(TenantAdminManager::class)->updateAdmin($tenant, $user, $data);
                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Aggiornamento admin fallito')
                        ->body($exception->getMessage())
                        ->send();

                    $action->halt();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Admin tenant aggiornato')
                    ->body("Utente {$user->email} aggiornato con successo.")
                    ->send();
            });
    }
}

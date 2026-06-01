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

class CreateTenantAdminAction
{
    public static function make(Closure $resolveTenant): Action
    {
        return Action::make('createTenantAdmin')
            ->label('Crea admin tenant')
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('primary')
            ->hidden(function (?Tenant $record = null) use ($resolveTenant): bool {
                $tenant = $resolveTenant($record);

                return $tenant instanceof Tenant
                    && app(TenantAdminManager::class)->getAdmin($tenant) !== null;
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
                    ->rule(Rule::unique(User::class, 'email'))
                    ->helperText('Questo utente fara il login centrale e vedra solo il tenant selezionato.'),
                TextInput::make('password')
                    ->label('Password iniziale')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8)
                    ->confirmed()
                    ->autocomplete('new-password'),
                TextInput::make('password_confirmation')
                    ->label('Conferma password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->same('password')
                    ->autocomplete('new-password'),
            ])
            ->modalHeading('Crea accesso admin tenant')
            ->modalDescription('Il login resta centralizzato. Questo utente verra collegato al tenant e non avra privilegi da superadmin.')
            ->modalSubmitActionLabel('Crea accesso')
            ->action(function (array $data, Action $action, ?Tenant $record = null) use ($resolveTenant): void {
                $tenant = $resolveTenant($record);

                abort_unless($tenant instanceof Tenant, 404);

                try {
                    $user = app(TenantAdminManager::class)->createAdmin($tenant, $data);
                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Creazione admin fallita')
                        ->body($exception->getMessage())
                        ->send();

                    $action->halt();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Accesso admin creato')
                    ->body("Utente {$user->email} collegato al tenant {$tenant->name}.")
                    ->send();
            });
    }
}

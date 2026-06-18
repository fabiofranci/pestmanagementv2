<?php

namespace App\Filament\Actions;

use App\Models\Customer;
use App\Models\User;
use App\Support\Tenancy\CustomerPortalUserManager;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;
use Throwable;

class CreateCustomerPortalUserAction
{
    public static function make(Closure $resolveCustomer): Action
    {
        return Action::make('createCustomerPortalUser')
            ->label('Crea accesso cliente')
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('primary')
            ->hidden(function (?Customer $record = null) use ($resolveCustomer): bool {
                $customer = $resolveCustomer($record);

                return ! $customer instanceof Customer
                    || app(CustomerPortalUserManager::class)->getUser($customer) !== null;
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
                    ->helperText('Questo utente usera l area riservata per consultare dati e documenti del cliente selezionato.'),
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
            ->modalHeading('Crea accesso area riservata')
            ->modalDescription('Il cliente potra accedere solo ai dati collegati alla propria anagrafica.')
            ->modalSubmitActionLabel('Crea accesso')
            ->action(function (array $data, Action $action, ?Customer $record = null) use ($resolveCustomer): void {
                $customer = $resolveCustomer($record);

                abort_unless($customer instanceof Customer, 404);

                try {
                    $user = app(CustomerPortalUserManager::class)->createUser($customer, $data);
                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Creazione accesso fallita')
                        ->body($exception->getMessage())
                        ->send();

                    $action->halt();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Accesso cliente creato')
                    ->body("Utente {$user->email} collegato al cliente {$customer->name}.")
                    ->send();
            });
    }
}

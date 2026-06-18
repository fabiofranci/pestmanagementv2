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

class EditCustomerPortalUserAction
{
    public static function make(Closure $resolveCustomer): Action
    {
        return Action::make('editCustomerPortalUser')
            ->label('Modifica accesso')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->hidden(function (?Customer $record = null) use ($resolveCustomer): bool {
                $customer = $resolveCustomer($record);

                return ! $customer instanceof Customer
                    || app(CustomerPortalUserManager::class)->getUser($customer) === null;
            })
            ->fillForm(function (?Customer $record = null) use ($resolveCustomer): array {
                $customer = $resolveCustomer($record);
                $user = $customer instanceof Customer
                    ? app(CustomerPortalUserManager::class)->getUser($customer)
                    : null;

                return [
                    'name' => $user?->name,
                    'email' => $user?->email,
                ];
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
                    ->rule(function (?Customer $record = null) use ($resolveCustomer): Rule {
                        $customer = $resolveCustomer($record);
                        $user = $customer instanceof Customer
                            ? app(CustomerPortalUserManager::class)->getUser($customer)
                            : null;

                        return Rule::unique(User::class, 'email')->ignore($user?->getKey());
                    }),
                TextInput::make('password')
                    ->label('Nuova password')
                    ->password()
                    ->revealable()
                    ->nullable()
                    ->minLength(8)
                    ->confirmed()
                    ->autocomplete('new-password')
                    ->helperText('Lascia vuoto per mantenere la password attuale.'),
                TextInput::make('password_confirmation')
                    ->label('Conferma nuova password')
                    ->password()
                    ->revealable()
                    ->nullable()
                    ->same('password')
                    ->autocomplete('new-password'),
            ])
            ->modalHeading('Modifica accesso area riservata')
            ->modalSubmitActionLabel('Salva accesso')
            ->action(function (array $data, Action $action, ?Customer $record = null) use ($resolveCustomer): void {
                $customer = $resolveCustomer($record);

                abort_unless($customer instanceof Customer, 404);

                $user = app(CustomerPortalUserManager::class)->getUser($customer);

                abort_unless($user instanceof User, 404);

                try {
                    $user = app(CustomerPortalUserManager::class)->updateUser($customer, $user, $data);
                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Aggiornamento accesso fallito')
                        ->body($exception->getMessage())
                        ->send();

                    $action->halt();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Accesso cliente aggiornato')
                    ->body("Utente {$user->email} aggiornato per il cliente {$customer->name}.")
                    ->send();
            });
    }
}

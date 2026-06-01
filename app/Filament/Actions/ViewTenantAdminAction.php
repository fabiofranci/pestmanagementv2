<?php

namespace App\Filament\Actions;

use App\Models\Tenant;
use App\Support\Tenancy\TenantAdminManager;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

class ViewTenantAdminAction
{
    public static function make(Closure $resolveTenant): Action
    {
        return Action::make('viewTenantAdmin')
            ->label('Vedi admin tenant')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->hidden(function (?Tenant $record = null) use ($resolveTenant): bool {
                $tenant = $resolveTenant($record);

                return ! $tenant instanceof Tenant
                    || app(TenantAdminManager::class)->getAdmin($tenant) === null;
            })
            ->schema([
                TextInput::make('name')
                    ->label('Nome'),
                TextInput::make('email')
                    ->label('Email')
                    ->email(),
            ])
            ->fillForm(function (?Tenant $record = null) use ($resolveTenant): array {
                $tenant = $resolveTenant($record);
                $user = $tenant instanceof Tenant ? app(TenantAdminManager::class)->getAdmin($tenant) : null;

                return [
                    'name' => $user?->name,
                    'email' => $user?->email,
                ];
            })
            ->disabledForm()
            ->modalHeading('Dettagli admin tenant')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Chiudi');
    }
}

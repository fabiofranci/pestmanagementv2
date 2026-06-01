<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Actions\CreateTenantAdminAction;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use App\Support\Filament\PanelAppearance;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantConnectionManager;
use App\Support\Tenancy\TenantDatabaseProvisioner;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Throwable;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = app(PanelAppearance::class)->applyTenantDefaults($data);
        $data['db_password'] = '';

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = app(PanelAppearance::class)->applyTenantDefaults($data);

        if (blank($data['db_database']) && filled($data['slug'])) {
            $data['db_database'] = app(TenantDatabaseProvisioner::class)->makeDefaultDatabaseName($data['slug']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateTenantAdminAction::make(fn (?Tenant $record = null) => $this->getRecord()),
            Action::make('entraNelTenant')
                ->label('Entra nel tenant')
                ->action(function (Action $action): void {
                    if (blank($this->getRecord()->db_database)) {
                        Notification::make()
                            ->danger()
                            ->title('Tenant non pronto')
                            ->body('Configura e provisiona prima il database del tenant.')
                            ->send();

                        $action->halt();

                        return;
                    }

                    try {
                        app(TenantConnectionManager::class)->activate($this->getRecord());
                        DB::purge(config('tenancy.database_connection'));
                        app(CurrentTenant::class)->activate($this->getRecord());
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Connessione tenant non valida')
                            ->body($exception->getMessage())
                            ->send();

                        $action->halt();
                    }
                })
                ->successRedirectUrl(fn (): string => CustomerResource::getUrl('index')),
            Action::make('provisionaDatabase')
                ->label('Provisiona database')
                ->action(function (): void {
                    try {
                        app(TenantDatabaseProvisioner::class)->provision($this->getRecord());

                        Notification::make()
                            ->success()
                            ->title('Database tenant provisionato')
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Provisioning fallito')
                            ->body($exception->getMessage())
                            ->send();
                    }
                }),
            DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Tenants\TenantResource;
use App\Support\Filament\PanelAppearance;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantDatabaseProvisioner;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Throwable;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected bool $tenantProvisioned = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = app(PanelAppearance::class)->applyTenantDefaults($data);

        if (blank($data['db_database']) && filled($data['slug'])) {
            $data['db_database'] = app(TenantDatabaseProvisioner::class)->makeDefaultDatabaseName($data['slug']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        try {
            app(TenantDatabaseProvisioner::class)->provision($this->getRecord());
            app(CurrentTenant::class)->activate($this->getRecord());

            $this->tenantProvisioned = true;
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Tenant creato, provisioning non completato')
                ->body($exception->getMessage())
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        if ($this->tenantProvisioned) {
            return CustomerResource::getUrl('index');
        }

        return TenantResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}

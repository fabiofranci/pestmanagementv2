<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected bool $createContractAfterSave = false;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            Action::make('createAndCreateContract')
                ->label('Salva e crea contratto')
                ->icon('heroicon-o-document-plus')
                ->action('createAndCreateContract'),
            $this->getCancelFormAction(),
        ];
    }

    public function createAndCreateContract(): void
    {
        $this->createContractAfterSave = true;

        $this->create();
    }

    protected function getRedirectUrl(): string
    {
        if (! $this->createContractAfterSave) {
            return parent::getRedirectUrl();
        }

        $defaultSiteId = $this->record
            ->sites()
            ->where('auto_created_from_customer', true)
            ->value('id');

        return ContractResource::getUrl('create', [
            'customer_id' => $this->record->getKey(),
            'customer_site_id' => $defaultSiteId,
        ]);
    }
}

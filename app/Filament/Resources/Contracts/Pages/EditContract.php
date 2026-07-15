<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Pages\Concerns\ManagesPrimaryContractService;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditContract extends EditRecord
{
    use ManagesPrimaryContractService;

    protected static string $resource = ContractResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['primary_service'] = $this->primaryContractServiceFormData($this->getRecord());

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->extractPrimaryContractServiceData($data);
    }

    protected function afterSave(): void
    {
        $this->savePrimaryContractService();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewContract')
                ->label('Riepilogo')
                ->url(fn (): string => ContractResource::getUrl('view', ['record' => $this->getRecord()])),
        ];
    }
}

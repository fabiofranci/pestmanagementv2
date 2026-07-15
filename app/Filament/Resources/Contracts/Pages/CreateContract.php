<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Pages\Concerns\ManagesPrimaryContractService;
use Filament\Resources\Pages\CreateRecord;

class CreateContract extends CreateRecord
{
    use ManagesPrimaryContractService;

    protected static string $resource = ContractResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->extractPrimaryContractServiceData($data);
    }

    protected function afterCreate(): void
    {
        $this->savePrimaryContractService();
    }
}

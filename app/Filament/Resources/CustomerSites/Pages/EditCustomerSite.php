<?php

namespace App\Filament\Resources\CustomerSites\Pages;

use App\Filament\Resources\CustomerSites\CustomerSiteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomerSite extends EditRecord
{
    protected static string $resource = CustomerSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

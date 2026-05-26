<?php

namespace App\Filament\Resources\CustomerSites\Pages;

use App\Filament\Resources\CustomerSites\CustomerSiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomerSites extends ListRecords
{
    protected static string $resource = CustomerSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

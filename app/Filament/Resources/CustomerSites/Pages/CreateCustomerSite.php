<?php

namespace App\Filament\Resources\CustomerSites\Pages;

use App\Filament\Resources\CustomerSites\CustomerSiteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerSite extends CreateRecord
{
    protected static string $resource = CustomerSiteResource::class;
}

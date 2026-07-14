<?php

namespace App\Filament\Resources\LeadFetchRunResource\Pages;

use App\Filament\Resources\LeadFetchRunResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLeadFetchRuns extends ListRecords
{
    protected static string $resource = LeadFetchRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

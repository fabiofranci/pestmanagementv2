<?php

namespace App\Filament\Resources\PestTypes\Pages;

use App\Filament\Resources\PestTypes\PestTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPestTypes extends ListRecords
{
    protected static string $resource = PestTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

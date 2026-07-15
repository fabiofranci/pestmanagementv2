<?php

namespace App\Filament\Resources\BillableItems\Pages;

use App\Filament\Resources\BillableItems\BillableItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBillableItems extends ListRecords
{
    protected static string $resource = BillableItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\BillableItems\Pages;

use App\Filament\Resources\BillableItems\BillableItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBillableItem extends EditRecord
{
    protected static string $resource = BillableItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

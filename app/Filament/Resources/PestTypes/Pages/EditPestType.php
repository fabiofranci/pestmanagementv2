<?php

namespace App\Filament\Resources\PestTypes\Pages;

use App\Filament\Resources\PestTypes\PestTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPestType extends EditRecord
{
    protected static string $resource = PestTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

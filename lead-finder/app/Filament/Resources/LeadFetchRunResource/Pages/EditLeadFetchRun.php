<?php

namespace App\Filament\Resources\LeadFetchRunResource\Pages;

use App\Filament\Resources\LeadFetchRunResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLeadFetchRun extends EditRecord
{
    protected static string $resource = LeadFetchRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditContract extends EditRecord
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewContract')
                ->label('Riepilogo')
                ->url(fn (): string => ContractResource::getUrl('view', ['record' => $this->getRecord()])),
        ];
    }
}

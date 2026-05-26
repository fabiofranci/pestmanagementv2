<?php

namespace App\Filament\Resources\MonitoringPoints\Pages;

use App\Filament\Resources\MonitoringPoints\MonitoringPointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMonitoringPoint extends EditRecord
{
    protected static string $resource = MonitoringPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\MonitoringPoints\Pages;

use App\Filament\Resources\MonitoringPoints\MonitoringPointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMonitoringPoints extends ListRecords
{
    protected static string $resource = MonitoringPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

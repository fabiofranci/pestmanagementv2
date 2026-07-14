<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class TotalLeadsWidget extends BaseWidget
{
    protected function getCards(): array
    {
        return [
            Card::make('Totale lead', Lead::count()),
        ];
    }
}

<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class ToContactLeadsWidget extends BaseWidget
{
    protected function getCards(): array
    {
        return [
            Card::make('Lead da contattare', Lead::where('status', 'to_contact')->count()),
        ];
    }
}

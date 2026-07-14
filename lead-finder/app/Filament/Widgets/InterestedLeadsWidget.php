<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class InterestedLeadsWidget extends BaseWidget
{
    protected function getCards(): array
    {
        return [
            Card::make('Lead interessati', Lead::where('status', 'interested')->count()),
        ];
    }
}

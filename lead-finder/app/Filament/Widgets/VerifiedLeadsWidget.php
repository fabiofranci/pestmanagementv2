<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class VerifiedLeadsWidget extends BaseWidget
{
    protected function getCards(): array
    {
        return [
            Card::make('Lead verificati', Lead::where('status', 'verified')->count()),
        ];
    }
}

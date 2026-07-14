<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use Filament\Widgets\BarChartWidget;

class LeadsByProvinceWidget extends BarChartWidget
{
    protected static ?string $heading = 'Lead per provincia';

    protected function getData(): array
    {
        $counts = Lead::select('province')
            ->whereNotNull('province')
            ->groupBy('province')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(10)
            ->pluck('province');

        $values = Lead::selectRaw('province, COUNT(*) as total')
            ->whereNotNull('province')
            ->groupBy('province')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'province');

        return [
            'labels' => $values->keys()->toArray(),
            'datasets' => [
                [
                    'label' => 'Lead',
                    'data' => $values->values()->toArray(),
                ],
            ],
        ];
    }
}

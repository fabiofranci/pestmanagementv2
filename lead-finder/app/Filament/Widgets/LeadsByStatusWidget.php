<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use Filament\Widgets\PieChartWidget;

class LeadsByStatusWidget extends PieChartWidget
{
    protected static ?string $heading = 'Lead per stato';

    protected function getData(): array
    {
        $statuses = Lead::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'datasets' => [
                [
                    'data' => $statuses->values()->toArray(),
                    'backgroundColor' => [
                        '#2563eb',
                        '#16a34a',
                        '#f59e0b',
                        '#64748b',
                        '#0ea5e9',
                        '#22c55e',
                        '#ef4444',
                        '#8b5cf6',
                        '#f97316',
                    ],
                ],
            ],
            'labels' => $statuses->keys()->toArray(),
        ];
    }
}

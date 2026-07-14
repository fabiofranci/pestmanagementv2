<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\InterestedLeadsWidget;
use App\Filament\Widgets\LeadsByProvinceWidget;
use App\Filament\Widgets\LeadsByStatusWidget;
use App\Filament\Widgets\ToContactLeadsWidget;
use App\Filament\Widgets\TotalLeadsWidget;
use App\Filament\Widgets\VerifiedLeadsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected function getWidgets(): array
    {
        return [
            TotalLeadsWidget::class,
            VerifiedLeadsWidget::class,
            ToContactLeadsWidget::class,
            InterestedLeadsWidget::class,
            LeadsByProvinceWidget::class,
            LeadsByStatusWidget::class,
        ];
    }
}

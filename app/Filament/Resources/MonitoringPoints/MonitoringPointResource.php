<?php

namespace App\Filament\Resources\MonitoringPoints;

use App\Filament\Resources\TenantScopedResource;
use App\Filament\Resources\MonitoringPoints\Pages\CreateMonitoringPoint;
use App\Filament\Resources\MonitoringPoints\Pages\EditMonitoringPoint;
use App\Filament\Resources\MonitoringPoints\Pages\ListMonitoringPoints;
use App\Filament\Resources\MonitoringPoints\Schemas\MonitoringPointForm;
use App\Filament\Resources\MonitoringPoints\Tables\MonitoringPointsTable;
use App\Models\MonitoringPoint;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MonitoringPointResource extends TenantScopedResource
{
    protected static ?string $model = MonitoringPoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?string $navigationLabel = 'Punti di monitoraggio';

    protected static ?string $modelLabel = 'punto di monitoraggio';

    protected static ?string $pluralModelLabel = 'punti di monitoraggio';

    public static function form(Schema $schema): Schema
    {
        return MonitoringPointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MonitoringPointsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMonitoringPoints::route('/'),
            'create' => CreateMonitoringPoint::route('/create'),
            'edit' => EditMonitoringPoint::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\PestTypes;

use App\Filament\Resources\PestTypes\Pages\CreatePestType;
use App\Filament\Resources\PestTypes\Pages\EditPestType;
use App\Filament\Resources\PestTypes\Pages\ListPestTypes;
use App\Filament\Resources\PestTypes\Schemas\PestTypeForm;
use App\Filament\Resources\PestTypes\Tables\PestTypesTable;
use App\Filament\Resources\TenantScopedResource;
use App\Models\PestType;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PestTypeResource extends TenantScopedResource
{
    protected static ?string $model = PestType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Tipi di infestante';

    protected static ?string $modelLabel = 'tipo di infestante';

    protected static ?string $pluralModelLabel = 'tipi di infestante';

    public static function form(Schema $schema): Schema
    {
        return PestTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PestTypesTable::configure($table);
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
            'index' => ListPestTypes::route('/'),
            'create' => CreatePestType::route('/create'),
            'edit' => EditPestType::route('/{record}/edit'),
        ];
    }
}

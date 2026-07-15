<?php

namespace App\Filament\Resources\BillableItems;

use App\Filament\Resources\BillableItems\Pages\CreateBillableItem;
use App\Filament\Resources\BillableItems\Pages\EditBillableItem;
use App\Filament\Resources\BillableItems\Pages\ListBillableItems;
use App\Filament\Resources\BillableItems\Schemas\BillableItemForm;
use App\Filament\Resources\BillableItems\Tables\BillableItemsTable;
use App\Filament\Resources\TenantScopedResource;
use App\Models\BillableItem;
use App\Support\Tenancy\TenantModules;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BillableItemResource extends TenantScopedResource
{
    protected static ?string $model = BillableItem::class;

    protected static ?string $tenantModule = TenantModules::BILLABLE_ITEMS;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Articoli fatturabili';

    protected static ?string $modelLabel = 'articolo fatturabile';

    protected static ?string $pluralModelLabel = 'articoli fatturabili';

    public static function form(Schema $schema): Schema
    {
        return BillableItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BillableItemsTable::configure($table);
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
            'index' => ListBillableItems::route('/'),
            'create' => CreateBillableItem::route('/create'),
            'edit' => EditBillableItem::route('/{record}/edit'),
        ];
    }
}

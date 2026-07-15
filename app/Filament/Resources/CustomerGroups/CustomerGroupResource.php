<?php

namespace App\Filament\Resources\CustomerGroups;

use App\Filament\Resources\CustomerGroups\Pages\CreateCustomerGroup;
use App\Filament\Resources\CustomerGroups\Pages\EditCustomerGroup;
use App\Filament\Resources\CustomerGroups\Pages\ListCustomerGroups;
use App\Filament\Resources\CustomerGroups\Schemas\CustomerGroupForm;
use App\Filament\Resources\CustomerGroups\Tables\CustomerGroupsTable;
use App\Filament\Resources\TenantScopedResource;
use App\Models\CustomerGroup;
use App\Support\Tenancy\TenantModules;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CustomerGroupResource extends TenantScopedResource
{
    protected static ?string $model = CustomerGroup::class;

    protected static ?string $tenantModule = TenantModules::CUSTOMER_GROUPS;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Gruppi clienti';

    protected static ?string $modelLabel = 'gruppo cliente';

    protected static ?string $pluralModelLabel = 'gruppi clienti';

    public static function form(Schema $schema): Schema
    {
        return CustomerGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerGroupsTable::configure($table);
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
            'index' => ListCustomerGroups::route('/'),
            'create' => CreateCustomerGroup::route('/create'),
            'edit' => EditCustomerGroup::route('/{record}/edit'),
        ];
    }
}

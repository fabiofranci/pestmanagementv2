<?php

namespace App\Filament\Resources\Contracts;

use App\Filament\Resources\TenantScopedResource;
use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Filament\Resources\Contracts\Pages\ListContracts;
use App\Filament\Resources\Contracts\Schemas\ContractForm;
use App\Filament\Resources\Contracts\Tables\ContractsTable;
use App\Models\Contract;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContractResource extends TenantScopedResource
{
    protected static ?string $model = Contract::class;

    protected static bool $allowsCustomerUsers = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'contract_number';

    protected static ?string $navigationLabel = 'Contratti';

    protected static ?string $modelLabel = 'contratto';

    protected static ?string $pluralModelLabel = 'contratti';

    public static function form(Schema $schema): Schema
    {
        return ContractForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContractsTable::configure($table);
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
            'index' => ListContracts::route('/'),
            'create' => CreateContract::route('/create'),
            'edit' => EditContract::route('/{record}/edit'),
        ];
    }
}

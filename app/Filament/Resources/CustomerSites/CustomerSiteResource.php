<?php

namespace App\Filament\Resources\CustomerSites;

use App\Filament\Resources\CustomerSites\Pages\CreateCustomerSite;
use App\Filament\Resources\CustomerSites\Pages\EditCustomerSite;
use App\Filament\Resources\CustomerSites\Pages\ListCustomerSites;
use App\Filament\Resources\CustomerSites\Schemas\CustomerSiteForm;
use App\Filament\Resources\CustomerSites\Tables\CustomerSitesTable;
use App\Models\CustomerSite;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CustomerSiteResource extends Resource
{
    protected static ?string $model = CustomerSite::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CustomerSiteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerSitesTable::configure($table);
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
            'index' => ListCustomerSites::route('/'),
            'create' => CreateCustomerSite::route('/create'),
            'edit' => EditCustomerSite::route('/{record}/edit'),
        ];
    }
}

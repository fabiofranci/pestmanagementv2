<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Actions\CustomerPortalUserActionGroup;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Resources\Customers\Tables\CustomersTable;
use App\Filament\Resources\TenantScopedResource;
use App\Models\Customer;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CustomerResource extends TenantScopedResource
{
    protected static ?string $model = Customer::class;

    protected static bool $allowsCustomerUsers = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Clienti';

    protected static ?string $modelLabel = 'cliente';

    protected static ?string $pluralModelLabel = 'clienti';

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
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
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function customerPortalUserActions(): ActionGroup
    {
        return CustomerPortalUserActionGroup::make(
            fn (?Customer $record = null): ?Customer => $record,
        );
    }

    public static function getNavigationLabel(): string
    {
        $user = auth()->user();

        if ($user instanceof User && $user->isTenantCustomer()) {
            return 'La tua azienda';
        }

        return static::$navigationLabel ?? 'Clienti';
    }
}

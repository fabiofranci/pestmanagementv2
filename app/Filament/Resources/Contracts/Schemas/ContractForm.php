<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Models\CustomerSite;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->label('Cliente')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('customer_site_id', null);
                    })
                    ->native(false)
                    ->required(),
                Select::make('customer_site_id')
                    ->label('Sede cliente')
                    ->options(fn (Get $get): array => CustomerSite::query()
                        ->when(
                            $get('customer_id'),
                            fn ($query, $customerId) => $query->where('customer_id', $customerId),
                            fn ($query) => $query->whereRaw('1 = 0'),
                        )
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required()
                    ->helperText('Seleziona prima il cliente per filtrare le sedi.'),
                TextInput::make('contract_number')
                    ->label('Numero contratto')
                    ->required(),
                TextInput::make('status')
                    ->label('Stato')
                    ->required()
                    ->default('active'),
                DatePicker::make('start_date')
                    ->label('Data inizio'),
                DatePicker::make('end_date')
                    ->label('Data fine'),
                TextInput::make('renewal')
                    ->label('Rinnovo'),
                TextInput::make('term')
                    ->label('Durata'),
                TextInput::make('payment_terms')
                    ->label('Condizioni di pagamento'),
                TextInput::make('total_value')
                    ->label('Valore totale')
                    ->numeric(),
                TextInput::make('currency')
                    ->label('Valuta'),
                Textarea::make('notes')
                    ->label('Note')
                    ->columnSpanFull(),
            ]);
    }
}

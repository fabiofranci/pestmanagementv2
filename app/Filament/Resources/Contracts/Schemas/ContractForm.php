<?php

namespace App\Filament\Resources\Contracts\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('customer_id')
                    ->label('Cliente')
                    ->required()
                    ->numeric(),
                TextInput::make('customer_site_id')
                    ->label('Sede cliente')
                    ->required()
                    ->numeric(),
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

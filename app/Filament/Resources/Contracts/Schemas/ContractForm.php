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
                TextInput::make('tenant_id')
                    ->required()
                    ->numeric(),
                TextInput::make('customer_id')
                    ->required()
                    ->numeric(),
                TextInput::make('customer_site_id')
                    ->required()
                    ->numeric(),
                TextInput::make('contract_number')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('active'),
                DatePicker::make('start_date'),
                DatePicker::make('end_date'),
                TextInput::make('renewal'),
                TextInput::make('term'),
                TextInput::make('payment_terms'),
                TextInput::make('total_value')
                    ->numeric(),
                TextInput::make('currency'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}

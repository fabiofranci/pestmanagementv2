<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                TextInput::make('legal_name')
                    ->label('Ragione sociale'),
                TextInput::make('tax_id')
                    ->label('Partita IVA / Codice fiscale'),
                TextInput::make('email')
                    ->label('Email')
                    ->email(),
                TextInput::make('phone')
                    ->label('Telefono')
                    ->tel(),
                TextInput::make('address')
                    ->label('Indirizzo'),
                TextInput::make('city')
                    ->label('Città'),
                TextInput::make('postcode')
                    ->label('CAP'),
                TextInput::make('province')
                    ->label('Provincia'),
                TextInput::make('country')
                    ->label('Paese'),
                Textarea::make('notes')
                    ->label('Note')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->label('Stato')
                    ->required()
                    ->default('active'),
            ]);
    }
}

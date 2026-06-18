<?php

namespace App\Filament\Resources\CustomerSites\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomerSiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('customer_id')
                    ->label('Cliente')
                    ->required()
                    ->numeric(),
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
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
                TextInput::make('contact_name')
                    ->label('Nome referente'),
                TextInput::make('contact_phone')
                    ->label('Telefono referente')
                    ->tel(),
                TextInput::make('contact_email')
                    ->label('Email referente')
                    ->email(),
                TextInput::make('site_code')
                    ->label('Codice sede'),
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

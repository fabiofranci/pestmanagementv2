<?php

namespace App\Filament\Resources\ServiceTypes\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ServiceTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                TextInput::make('code')
                    ->label('Codice'),
                Textarea::make('description')
                    ->label('Descrizione')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->label('Stato')
                    ->required()
                    ->default('active'),
            ]);
    }
}

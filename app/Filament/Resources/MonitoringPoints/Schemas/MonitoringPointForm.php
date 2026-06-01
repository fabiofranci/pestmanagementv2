<?php

namespace App\Filament\Resources\MonitoringPoints\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MonitoringPointForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('area_id')
                    ->label('Area')
                    ->required()
                    ->numeric(),
                TextInput::make('code')
                    ->label('Codice')
                    ->required(),
                TextInput::make('name')
                    ->label('Nome'),
                TextInput::make('service_type_id')
                    ->label('Tipo di servizio')
                    ->required()
                    ->numeric(),
                TextInput::make('type')
                    ->label('Tipo'),
                TextInput::make('model')
                    ->label('Modello'),
                TextInput::make('product')
                    ->label('Prodotto'),
                TextInput::make('latitude')
                    ->label('Latitudine')
                    ->numeric(),
                TextInput::make('longitude')
                    ->label('Longitudine')
                    ->numeric(),
                Textarea::make('map_position')
                    ->label('Posizione sulla mappa')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->label('Stato')
                    ->required()
                    ->default('active'),
            ]);
    }
}

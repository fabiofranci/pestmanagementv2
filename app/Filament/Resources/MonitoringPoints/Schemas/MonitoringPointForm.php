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
                TextInput::make('tenant_id')
                    ->required()
                    ->numeric(),
                TextInput::make('area_id')
                    ->required()
                    ->numeric(),
                TextInput::make('code')
                    ->required(),
                TextInput::make('name'),
                TextInput::make('service_type_id')
                    ->required()
                    ->numeric(),
                TextInput::make('type'),
                TextInput::make('model'),
                TextInput::make('product'),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
                    ->numeric(),
                Textarea::make('map_position')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required()
                    ->default('active'),
            ]);
    }
}

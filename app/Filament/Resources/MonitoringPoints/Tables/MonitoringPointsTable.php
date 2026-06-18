<?php

namespace App\Filament\Resources\MonitoringPoints\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MonitoringPointsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('area_id')
                    ->label('Area')
                    ->state(fn ($record): ?string => $record->area?->name)
                    ->searchable(),
                TextColumn::make('code')
                    ->label('Codice')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('service_type_id')
                    ->label('Tipo di servizio')
                    ->state(fn ($record): ?string => $record->serviceType?->name)
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->searchable(),
                TextColumn::make('model')
                    ->label('Modello')
                    ->searchable(),
                TextColumn::make('product')
                    ->label('Prodotto')
                    ->searchable(),
                TextColumn::make('latitude')
                    ->label('Latitudine')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('longitude')
                    ->label('Longitudine')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Aggiornato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

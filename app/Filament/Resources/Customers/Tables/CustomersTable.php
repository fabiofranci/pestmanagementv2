<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('legal_name')
                    ->label('Ragione sociale')
                    ->searchable(),
                TextColumn::make('tax_id')
                    ->label('Partita IVA / Codice fiscale')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telefono')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Indirizzo')
                    ->searchable(),
                TextColumn::make('city')
                    ->label('Città')
                    ->searchable(),
                TextColumn::make('postcode')
                    ->label('CAP')
                    ->searchable(),
                TextColumn::make('province')
                    ->label('Provincia')
                    ->searchable(),
                TextColumn::make('country')
                    ->label('Paese')
                    ->searchable(),
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

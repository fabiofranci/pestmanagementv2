<?php

namespace App\Filament\Resources\CustomerSites\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomerSitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer_id')
                    ->label('Cliente')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nome')
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
                TextColumn::make('contact_name')
                    ->label('Nome referente')
                    ->searchable(),
                TextColumn::make('contact_phone')
                    ->label('Telefono referente')
                    ->searchable(),
                TextColumn::make('contact_email')
                    ->label('Email referente')
                    ->searchable(),
                TextColumn::make('site_code')
                    ->label('Codice sede')
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

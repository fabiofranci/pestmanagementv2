<?php

namespace App\Filament\Resources\CustomerGroups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomerGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Codice')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'active' => 'Attivo',
                        'inactive' => 'Inattivo',
                        default => $state ?: '-',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('customers_count')
                    ->label('Clienti')
                    ->counts('customers')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Aggiornato il')
                    ->dateTime()
                    ->sortable(),
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

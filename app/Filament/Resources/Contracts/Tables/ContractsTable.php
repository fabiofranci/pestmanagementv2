<?php

namespace App\Filament\Resources\Contracts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer_id')
                    ->label('Cliente')
                    ->state(fn ($record): ?string => $record->customer?->name)
                    ->searchable(),
                TextColumn::make('customer_site_id')
                    ->label('Sede cliente')
                    ->state(fn ($record): ?string => $record->site?->name)
                    ->searchable(),
                TextColumn::make('contract_number')
                    ->label('Numero contratto')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->searchable(),
                TextColumn::make('start_date')
                    ->label('Data inizio')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('Data fine')
                    ->date()
                    ->sortable(),
                TextColumn::make('renewal')
                    ->label('Rinnovo')
                    ->searchable(),
                TextColumn::make('term')
                    ->label('Durata')
                    ->searchable(),
                TextColumn::make('payment_terms')
                    ->label('Condizioni di pagamento')
                    ->searchable(),
                TextColumn::make('total_value')
                    ->label('Valore totale')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('currency')
                    ->label('Valuta')
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

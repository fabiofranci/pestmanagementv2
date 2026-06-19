<?php

namespace App\Filament\Resources\Contracts\Tables;

use App\Models\Customer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contract_number')
                    ->label('Numero contratto')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable(),
                TextColumn::make('site.name')
                    ->label('Sede')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => 'Bozza',
                        'active' => 'Attivo',
                        'suspended' => 'Sospeso',
                        'closed' => 'Chiuso',
                        'cancelled' => 'Annullato',
                        default => $state ?: '-',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'draft' => 'gray',
                        'suspended' => 'warning',
                        'closed' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label('Data inizio')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('Data fine')
                    ->date()
                    ->sortable(),
                TextColumn::make('total_value')
                    ->label('Valore totale')
                    ->money(fn ($record): string => $record->currency ?: 'EUR')
                    ->sortable(),
                TextColumn::make('payment_terms')
                    ->label('Condizioni pagamento')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('renewal')
                    ->label('Rinnovo')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('term')
                    ->label('Durata')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')
                    ->label('Note')
                    ->limit(60)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('currency')
                    ->label('Valuta')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'draft' => 'Bozza',
                        'active' => 'Attivo',
                        'suspended' => 'Sospeso',
                        'closed' => 'Chiuso',
                        'cancelled' => 'Annullato',
                    ])
                    ->native(false),
                SelectFilter::make('customer_id')
                    ->label('Cliente')
                    ->options(fn (): array => Customer::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->native(false),
                Filter::make('scadenza')
                    ->label('Scadenza')
                    ->schema([
                        DatePicker::make('end_from')
                            ->label('Fine da'),
                        DatePicker::make('end_until')
                            ->label('Fine a'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['end_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('end_date', '>=', $date))
                        ->when($data['end_until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('end_date', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

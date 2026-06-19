<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContractBillingSchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'billingSchedules';

    protected static ?string $title = 'Piano fatturazione';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('description')
                    ->label('Descrizione')
                    ->required(),
                DatePicker::make('due_date')
                    ->label('Scadenza')
                    ->required(),
                TextInput::make('amount')
                    ->label('Importo')
                    ->numeric()
                    ->required(),
                TextInput::make('currency')
                    ->label('Valuta')
                    ->default('EUR')
                    ->maxLength(3)
                    ->required(),
                TextInput::make('vat_rate')
                    ->label('IVA %')
                    ->numeric(),
                Select::make('status')
                    ->label('Stato')
                    ->options([
                        'planned' => 'Pianificata',
                        'issued' => 'Emessa',
                        'paid' => 'Pagata',
                        'cancelled' => 'Annullata',
                    ])
                    ->default('planned')
                    ->native(false)
                    ->required(),
                TextInput::make('invoice_reference')
                    ->label('Riferimento fattura'),
                Textarea::make('notes')
                    ->label('Note')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->searchable(),
                TextColumn::make('due_date')
                    ->label('Scadenza')
                    ->date()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Importo')
                    ->money(fn ($record): string => $record->currency ?: 'EUR')
                    ->sortable(),
                TextColumn::make('currency')
                    ->label('Valuta')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Stato')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'planned' => 'Pianificata',
                        'issued' => 'Emessa',
                        'paid' => 'Pagata',
                        'cancelled' => 'Annullata',
                        default => $state ?: '-',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'planned' => 'gray',
                        'issued' => 'info',
                        'paid' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('invoice_reference')
                    ->label('Rif. fattura')
                    ->placeholder('-')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'planned' => 'Pianificata',
                        'issued' => 'Emessa',
                        'paid' => 'Pagata',
                        'cancelled' => 'Annullata',
                    ])
                    ->native(false),
            ])
            ->defaultSort('due_date')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

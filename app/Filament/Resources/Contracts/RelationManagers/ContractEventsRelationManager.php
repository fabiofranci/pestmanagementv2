<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContractEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $title = 'Storico';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('created_by_user_id')
                    ->default(fn (): ?int => auth()->id()),
                Select::make('event_type')
                    ->label('Tipo evento')
                    ->options([
                        'manual' => 'Manuale',
                        'note' => 'Nota',
                        'created' => 'Creazione',
                        'closed' => 'Chiusura',
                        'reactivated' => 'Riattivazione',
                        'duplicated' => 'Duplicazione',
                        'renewed' => 'Rinnovo',
                        'created_from_renewal' => 'Creato da rinnovo',
                        'cancelled' => 'Disdetta',
                        'status_changed' => 'Cambio stato',
                    ])
                    ->default('manual')
                    ->native(false)
                    ->required(),
                TextInput::make('title')
                    ->label('Titolo')
                    ->required()
                    ->maxLength(255),
                KeyValue::make('payload')
                    ->label('Dati')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('event_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'manual' => 'Manuale',
                        'note' => 'Nota',
                        'created' => 'Creazione',
                        'closed' => 'Chiusura',
                        'reactivated' => 'Riattivazione',
                        'duplicated' => 'Duplicazione',
                        'renewed' => 'Rinnovo',
                        'created_from_renewal' => 'Creato da rinnovo',
                        'cancelled' => 'Disdetta',
                        'status_changed' => 'Cambio stato',
                        default => $state ?: '-',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'closed' => 'warning',
                        'reactivated' => 'success',
                        'duplicated' => 'info',
                        'renewed', 'created_from_renewal' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
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

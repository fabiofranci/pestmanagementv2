<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
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
                TextInput::make('event_type')
                    ->label('Tipo evento')
                    ->required(),
                TextInput::make('title')
                    ->label('Titolo')
                    ->required(),
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
                    ->searchable(),
                TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->sortable(),
            ])
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

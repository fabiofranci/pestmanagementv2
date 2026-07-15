<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomerBillableItemPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'billableItemPrices';

    protected static ?string $title = 'Prezzi articoli';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('billable_item_id')
                    ->label('Articolo fatturabile')
                    ->relationship('billableItem', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                TextInput::make('discount_percentage')
                    ->label('Sconto %')
                    ->numeric()
                    ->suffix('%'),
                TextInput::make('custom_unit_price')
                    ->label('Prezzo personalizzato')
                    ->numeric()
                    ->prefix('EUR')
                    ->helperText('Se valorizzato, ha priorita sullo sconto.'),
                Textarea::make('notes')
                    ->label('Note')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('billableItem.name')
            ->columns([
                TextColumn::make('billableItem.name')
                    ->label('Articolo')
                    ->searchable(),
                TextColumn::make('billableItem.default_unit_price')
                    ->label('Prezzo standard')
                    ->money('EUR')
                    ->placeholder('-'),
                TextColumn::make('discount_percentage')
                    ->label('Sconto %')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),
                TextColumn::make('custom_unit_price')
                    ->label('Prezzo personalizzato')
                    ->money('EUR')
                    ->placeholder('-'),
                TextColumn::make('notes')
                    ->label('Note')
                    ->limit(50)
                    ->placeholder('-')
                    ->toggleable(),
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

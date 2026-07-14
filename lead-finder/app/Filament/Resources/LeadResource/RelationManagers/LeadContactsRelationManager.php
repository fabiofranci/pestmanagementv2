<?php

namespace App\Filament\Resources\LeadResource\RelationManagers;

use App\Models\LeadContact;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LeadContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    protected static ?string $recordTitleAttribute = 'value';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('type')->required()->maxLength(50),
                TextInput::make('value')->required()->maxLength(255),
                TextInput::make('label')->maxLength(255),
                TextInput::make('source_url')->url()->maxLength(2048),
                Toggle::make('is_primary'),
                Toggle::make('is_valid'),
                Textarea::make('notes')->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->sortable(),
                TextColumn::make('value')->sortable()->searchable(),
                TextColumn::make('label')->sortable(),
                TextColumn::make('source_url')->url()->limit(40),
                TextColumn::make('is_primary')->boolean(),
                TextColumn::make('is_valid')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_primary')->label('Primary'),
                Tables\Filters\TernaryFilter::make('is_valid')->label('Valid'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}

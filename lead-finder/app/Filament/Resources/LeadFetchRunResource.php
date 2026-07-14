<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeadFetchRunResource\Pages;
use App\Filament\Resources\LeadFetchRunResource\RelationManagers;
use App\Models\LeadFetchRun;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LeadFetchRunResource extends Resource
{
    protected static ?string $model = LeadFetchRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Lead Fetch Runs';
    protected static ?string $navigationGroup = 'CRM';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('search_query')->label('Query')->maxLength(255),
                TextInput::make('region')->maxLength(100),
                TextInput::make('province')->maxLength(100),
                TextInput::make('sector')->maxLength(100),
                TextInput::make('found_count')->numeric(),
                TextInput::make('created_count')->numeric(),
                TextInput::make('updated_count')->numeric(),
                TextInput::make('error_count')->numeric(),
                TextInput::make('status')->maxLength(50),
                Textarea::make('error_message')->rows(3),
                DateTimePicker::make('started_at'),
                DateTimePicker::make('finished_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('query')->limit(40),
                TextColumn::make('region')->sortable(),
                TextColumn::make('province')->sortable(),
                TextColumn::make('sector')->sortable(),
                TextColumn::make('found_count')->sortable(),
                TextColumn::make('created_count')->sortable(),
                TextColumn::make('updated_count')->sortable(),
                TextColumn::make('error_count')->sortable(),
                BadgeColumn::make('status'),
                TextColumn::make('started_at')->dateTime()->sortable(),
                TextColumn::make('finished_at')->dateTime()->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeadFetchRuns::route('/'),
            'create' => Pages\CreateLeadFetchRun::route('/create'),
            'edit' => Pages\EditLeadFetchRun::route('/{record}/edit'),
        ];
    }
}

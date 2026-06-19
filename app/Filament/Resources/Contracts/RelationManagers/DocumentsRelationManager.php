<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documenti';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('uploaded_by_user_id')
                    ->default(fn (): ?int => auth()->id()),
                Select::make('type')
                    ->label('Tipo')
                    ->options([
                        'contract' => 'Contratto',
                        'technical_sheet' => 'Scheda tecnica',
                        'safety_sheet' => 'Scheda sicurezza',
                        'report' => 'Rapportino',
                        'attachment' => 'Allegato',
                    ])
                    ->native(false)
                    ->required(),
                TextInput::make('title')
                    ->label('Titolo')
                    ->required()
                    ->maxLength(255),
                TextInput::make('file_path')
                    ->label('Percorso file'),
                TextInput::make('mime_type')
                    ->label('MIME type'),
                TextInput::make('size')
                    ->label('Dimensione')
                    ->numeric(),
                Toggle::make('visible_to_customer')
                    ->label('Visibile al cliente')
                    ->default(false),
                DateTimePicker::make('generated_at')
                    ->label('Generato il')
                    ->seconds(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'contract' => 'Contratto',
                        'technical_sheet' => 'Scheda tecnica',
                        'safety_sheet' => 'Scheda sicurezza',
                        'report' => 'Rapportino',
                        'attachment' => 'Allegato',
                        default => $state ?: '-',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'contract' => 'info',
                        'technical_sheet', 'safety_sheet' => 'warning',
                        'report' => 'success',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable(),
                TextColumn::make('file_path')
                    ->label('File')
                    ->placeholder('-')
                    ->searchable(),
                IconColumn::make('visible_to_customer')
                    ->label('Cliente')
                    ->boolean(),
                TextColumn::make('generated_at')
                    ->label('Generato il')
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

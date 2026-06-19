<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use App\Models\Area;
use App\Models\CustomerSite;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContractServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'services';

    protected static ?string $title = 'Servizi contrattuali';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('service_type_id')
                    ->label('Tipo di servizio')
                    ->relationship('serviceType', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                Select::make('customer_site_id')
                    ->label('Sede cliente')
                    ->options(fn (): array => CustomerSite::query()
                        ->where('customer_id', $this->getOwnerRecord()->customer_id)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('area_id', null);
                    })
                    ->native(false),
                Select::make('area_id')
                    ->label('Area')
                    ->options(fn (Get $get): array => Area::query()
                        ->when(
                            $get('customer_site_id'),
                            fn ($query, $siteId) => $query->where('customer_site_id', $siteId),
                            fn ($query) => $query->whereRaw('1 = 0'),
                        )
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->native(false),
                Textarea::make('description')
                    ->label('Descrizione')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('frequency')
                    ->label('Frequenza'),
                TextInput::make('quantity')
                    ->label('Quantita')
                    ->numeric(),
                TextInput::make('unit_price')
                    ->label('Prezzo unitario')
                    ->numeric(),
                TextInput::make('total_price')
                    ->label('Totale')
                    ->numeric(),
                TextInput::make('currency')
                    ->label('Valuta')
                    ->default('EUR')
                    ->maxLength(3)
                    ->required(),
                DatePicker::make('starts_on')
                    ->label('Decorrenza'),
                DatePicker::make('ends_on')
                    ->label('Fine validita'),
                Select::make('status')
                    ->label('Stato')
                    ->options([
                        'active' => 'Attivo',
                        'suspended' => 'Sospeso',
                        'closed' => 'Chiuso',
                    ])
                    ->default('active')
                    ->native(false)
                    ->required(),
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
                TextColumn::make('serviceType.name')
                    ->label('Tipo servizio')
                    ->searchable(),
                TextColumn::make('site.name')
                    ->label('Sede')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('area.name')
                    ->label('Area')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('frequency')
                    ->label('Frequenza')
                    ->placeholder('-'),
                TextColumn::make('total_price')
                    ->label('Totale')
                    ->money(fn ($record): string => $record->currency ?: 'EUR')
                    ->sortable(),
                TextColumn::make('currency')
                    ->label('Valuta')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Stato')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'active' => 'Attivo',
                        'suspended' => 'Sospeso',
                        'closed' => 'Chiuso',
                        default => $state ?: '-',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'closed' => 'info',
                        default => 'gray',
                    })
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'active' => 'Attivo',
                        'suspended' => 'Sospeso',
                        'closed' => 'Chiuso',
                    ])
                    ->native(false),
            ])
            ->defaultSort('id')
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

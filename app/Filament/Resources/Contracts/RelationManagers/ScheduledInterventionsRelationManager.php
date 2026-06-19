<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use App\Models\ContractService;
use App\Models\CustomerSite;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ScheduledInterventionsRelationManager extends RelationManager
{
    protected static string $relationship = 'scheduledInterventions';

    protected static ?string $title = 'Interventi programmati';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('contract_service_id')
                    ->label('Servizio contrattuale')
                    ->options(fn (): array => ContractService::query()
                        ->where('contract_id', $this->getOwnerRecord()->getKey())
                        ->orderBy('id')
                        ->pluck('description', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->native(false),
                Select::make('customer_site_id')
                    ->label('Sede cliente')
                    ->options(fn (): array => CustomerSite::query()
                        ->where('customer_id', $this->getOwnerRecord()->customer_id)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                Select::make('service_type_id')
                    ->label('Tipo di servizio')
                    ->relationship('serviceType', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                DatePicker::make('planned_date')
                    ->label('Data prevista')
                    ->required(),
                TimePicker::make('planned_time')
                    ->label('Ora prevista')
                    ->seconds(false),
                Select::make('status')
                    ->label('Stato')
                    ->options([
                        'planned' => 'Pianificato',
                        'confirmed' => 'Confermato',
                        'completed' => 'Completato',
                        'cancelled' => 'Annullato',
                    ])
                    ->default('planned')
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
            ->columns([
                TextColumn::make('planned_date')
                    ->label('Data')
                    ->date()
                    ->sortable(),
                TextColumn::make('planned_time')
                    ->label('Ora')
                    ->placeholder('-'),
                TextColumn::make('serviceType.name')
                    ->label('Tipo servizio')
                    ->searchable(),
                TextColumn::make('site.name')
                    ->label('Sede')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'planned' => 'Pianificato',
                        'confirmed' => 'Confermato',
                        'completed' => 'Completato',
                        'cancelled' => 'Annullato',
                        default => $state ?: '-',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'planned' => 'gray',
                        'confirmed' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'planned' => 'Pianificato',
                        'confirmed' => 'Confermato',
                        'completed' => 'Completato',
                        'cancelled' => 'Annullato',
                    ])
                    ->native(false),
            ])
            ->defaultSort('planned_date')
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

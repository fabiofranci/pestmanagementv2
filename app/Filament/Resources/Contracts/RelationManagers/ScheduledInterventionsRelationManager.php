<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use App\Models\ContractService;
use App\Models\CustomerSite;
use App\Models\ScheduledIntervention;
use Filament\Actions\Action;
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
use Filament\Schemas\Components\Utilities\Set;
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
                    ->live()
                    ->default(fn (): ?int => $this->singleContractService()?->getKey())
                    ->afterStateUpdated(function (?int $state, Set $set): void {
                        if (! $state) {
                            return;
                        }

                        $service = ContractService::query()
                            ->where('contract_id', $this->getOwnerRecord()->getKey())
                            ->find($state);

                        if (! $service) {
                            return;
                        }

                        $set('service_type_id', $service->service_type_id);
                        $set('customer_site_id', $service->customer_site_id ?: $this->getOwnerRecord()->customer_site_id);
                    })
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
                    ->default(fn (): ?int => $this->singleContractService()?->customer_site_id ?: $this->getOwnerRecord()->customer_site_id)
                    ->required(),
                Select::make('service_type_id')
                    ->label('Tipo di servizio')
                    ->relationship('serviceType', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->default(fn (): ?int => $this->singleContractService()?->service_type_id)
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
                TextColumn::make('notes')
                    ->label('Note')
                    ->limit(50)
                    ->placeholder('-')
                    ->toggleable(),
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
                Action::make('cancel')
                    ->label('Annulla')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (ScheduledIntervention $record): bool => $record->status !== 'cancelled')
                    ->action(fn (ScheduledIntervention $record): bool => $record->update(['status' => 'cancelled'])),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function singleContractService(): ?ContractService
    {
        $services = ContractService::query()
            ->where('contract_id', $this->getOwnerRecord()->getKey())
            ->limit(2)
            ->get();

        return $services->count() === 1 ? $services->first() : null;
    }
}

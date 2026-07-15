<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use App\Models\BillableItem;
use App\Models\Contract;
use App\Models\ScheduledIntervention;
use App\Support\Billing\InterventionBillableItemService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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

class InterventionBillableItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'interventionBillableItems';

    protected static ?string $title = 'Extra fatturabili interventi';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('scheduled_intervention_id')
                    ->label('Intervento')
                    ->options(fn (): array => $this->interventionOptions())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get): void {
                        if (filled($get('billable_item_id'))) {
                            $this->applySuggestedItemState($set, $get, $get('billable_item_id'));
                        }
                    })
                    ->native(false)
                    ->required(),
                Select::make('billable_item_id')
                    ->label('Articolo')
                    ->relationship('billableItem', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                        $this->applySuggestedItemState($set, $get, $state);
                    })
                    ->native(false),
                TextInput::make('description')
                    ->label('Descrizione')
                    ->required(),
                TextInput::make('quantity')
                    ->label('Quantita')
                    ->numeric()
                    ->default(1)
                    ->live()
                    ->afterStateUpdated(fn (Set $set, Get $get): mixed => $this->updateTotalPrice($set, $get)),
                TextInput::make('unit_price')
                    ->label('Prezzo unitario')
                    ->numeric()
                    ->live()
                    ->afterStateUpdated(fn (Set $set, Get $get): mixed => $this->updateTotalPrice($set, $get)),
                TextInput::make('total_price')
                    ->label('Totale')
                    ->numeric()
                    ->helperText('Calcolato come quantita x prezzo unitario. Resta modificabile manualmente.'),
                Select::make('status')
                    ->label('Stato')
                    ->options(static::statusOptions())
                    ->default('pending')
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
                TextColumn::make('scheduledIntervention.planned_date')
                    ->label('Intervento')
                    ->date()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->searchable(),
                TextColumn::make('billableItem.name')
                    ->label('Articolo')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Quantita')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('unit_price')
                    ->label('Prezzo unitario')
                    ->money(fn ($record): string => $record->contract?->currency ?: 'EUR')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('total_price')
                    ->label('Totale')
                    ->money(fn ($record): string => $record->contract?->currency ?: 'EUR')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->formatStateUsing(fn (?string $state): string => static::formatStatus($state))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'added_to_invoice' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('contractBillingSchedule.due_date')
                    ->label('Scadenza collegata')
                    ->date()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Aggiornato il')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options(static::statusOptions())
                    ->native(false),
            ])
            ->defaultSort('updated_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'tenant_id' => $this->getOwnerRecord()->tenant_id,
                        'contract_id' => $this->getOwnerRecord()->getKey(),
                    ]),
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

    protected function applySuggestedItemState(Set $set, Get $get, mixed $billableItemId): void
    {
        $item = filled($billableItemId)
            ? BillableItem::query()->find($billableItemId)
            : null;

        if (! $item) {
            $set('unit_price', null);
            $this->updateTotalPrice($set, $get);

            return;
        }

        $intervention = filled($get('scheduled_intervention_id'))
            ? ScheduledIntervention::query()
                ->where('contract_id', $this->getOwnerRecord()->getKey())
                ->find($get('scheduled_intervention_id'))
            : null;

        $service = app(InterventionBillableItemService::class);
        $suggestedState = $intervention
            ? $service->suggestedStateForIntervention($intervention, $item, $get('quantity'), $get('description'))
            : $service->suggestedStateForContract($this->ownerContract(), $item, $get('quantity'), $get('description'));

        $set('description', $suggestedState['description']);
        $set('unit_price', $suggestedState['unit_price']);
        $set('total_price', $suggestedState['total_price']);
    }

    protected function updateTotalPrice(Set $set, Get $get, mixed $unitPrice = null): void
    {
        $set('total_price', app(InterventionBillableItemService::class)->calculateTotal(
            $get('quantity'),
            $unitPrice ?? $get('unit_price'),
        ));
    }

    protected function interventionOptions(): array
    {
        return ScheduledIntervention::query()
            ->where('contract_id', $this->getOwnerRecord()->getKey())
            ->orderBy('planned_date')
            ->orderBy('planned_time')
            ->get()
            ->mapWithKeys(fn (ScheduledIntervention $intervention): array => [
                $intervention->getKey() => trim(($intervention->planned_date?->format('d/m/Y') ?? '-').' '.($intervention->planned_time ?? '').' - '.static::formatInterventionStatus($intervention->status)),
            ])
            ->all();
    }

    protected function ownerContract(): Contract
    {
        /** @var Contract $contract */
        $contract = $this->getOwnerRecord();

        return $contract;
    }

    protected static function statusOptions(): array
    {
        return [
            'pending' => 'Pending',
            'added_to_invoice' => 'Aggiunto a scadenza',
            'cancelled' => 'Annullato',
        ];
    }

    protected static function formatStatus(?string $state): string
    {
        return static::statusOptions()[$state] ?? ($state ?: '-');
    }

    protected static function formatInterventionStatus(?string $state): string
    {
        return match ($state) {
            'planned' => 'Pianificato',
            'confirmed' => 'Confermato',
            'completed' => 'Completato',
            'cancelled' => 'Annullato',
            default => $state ?: '-',
        };
    }
}

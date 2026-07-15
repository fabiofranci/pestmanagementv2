<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use App\Models\BillableItem;
use App\Models\Contract;
use App\Support\Billing\ContractBillableItemPricingService;
use App\Support\Contracts\ContractTotalsService;
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
use Filament\Tables\Table;

class ContractBillableItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'contractBillableItems';

    protected static ?string $title = 'Elementi fatturabili';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('billable_item_id')
                    ->label('Articolo')
                    ->relationship('billableItem', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                        $this->applySuggestedPrice($set, $get, $state);
                    })
                    ->native(false)
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
                TextInput::make('discount_percentage')
                    ->label('Sconto %')
                    ->numeric()
                    ->suffix('%')
                    ->helperText('Informativo: il prezzo unitario rappresenta il prezzo finale applicato al contratto.'),
                TextInput::make('total_price')
                    ->label('Totale')
                    ->numeric()
                    ->helperText('Calcolato come quantita x prezzo unitario. Resta modificabile manualmente.'),
                Select::make('status')
                    ->label('Stato')
                    ->options([
                        'active' => 'Attivo',
                        'inactive' => 'Inattivo',
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
            ->recordTitleAttribute('billableItem.name')
            ->columns([
                TextColumn::make('billableItem.name')
                    ->label('Articolo')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Quantita')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('unit_price')
                    ->label('Prezzo unitario')
                    ->money('EUR')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('discount_percentage')
                    ->label('Sconto %')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),
                TextColumn::make('total_price')
                    ->label('Totale')
                    ->money('EUR')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'active' => 'Attivo',
                        'inactive' => 'Inattivo',
                        default => $state ?: '-',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label('Aggiornato il')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->after(fn (): Contract => $this->recalculateOwnerContractTotal()),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(fn (): Contract => $this->recalculateOwnerContractTotal()),
                DeleteAction::make()
                    ->after(fn (): Contract => $this->recalculateOwnerContractTotal()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->after(fn (): Contract => $this->recalculateOwnerContractTotal()),
                ]),
            ]);
    }

    protected function applySuggestedPrice(Set $set, Get $get, mixed $billableItemId): void
    {
        $item = filled($billableItemId)
            ? BillableItem::query()->find($billableItemId)
            : null;

        if (! $item) {
            $set('unit_price', null);
            $set('discount_percentage', null);
            $this->updateTotalPrice($set, $get);

            return;
        }

        /** @var Contract $contract */
        $contract = $this->getOwnerRecord();
        $suggestedState = app(ContractBillableItemPricingService::class)
            ->suggestedStateForContract($contract, $item, $get('quantity'));

        $set('unit_price', $suggestedState['unit_price']);
        $set('discount_percentage', $suggestedState['discount_percentage']);
        $set('total_price', $suggestedState['total_price']);
    }

    protected function updateTotalPrice(Set $set, Get $get, mixed $unitPrice = null): void
    {
        $set('total_price', app(ContractBillableItemPricingService::class)->calculateTotal(
            $get('quantity'),
            $unitPrice ?? $get('unit_price'),
        ));
    }

    protected function recalculateOwnerContractTotal(): Contract
    {
        /** @var Contract $contract */
        $contract = $this->getOwnerRecord();

        return app(ContractTotalsService::class)->updateContractTotal($contract);
    }
}

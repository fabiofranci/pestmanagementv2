<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use App\Models\Area;
use App\Models\Contract;
use App\Models\CustomerSite;
use App\Models\Tenant;
use App\Support\Contracts\ContractTotalsService;
use App\Support\Tenancy\CurrentTenant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ContractServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'services';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return static::usesSingleServiceModeFor($ownerRecord)
            ? 'Servizio principale'
            : 'Servizi contrattuali';
    }

    protected function canCreate(): bool
    {
        return parent::canCreate() && $this->canCreateAnotherService();
    }

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
                Select::make('operational_schedule_mode')
                    ->label('Programmazione interventi')
                    ->options(static::scheduleModeOptions())
                    ->default('recurring')
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        if ($state !== 'recurring') {
                            $set('operational_frequency', null);
                        }

                        if ($state !== 'custom_months') {
                            $set('scheduled_months', null);
                            $set('interventions_per_year', null);
                        }
                    })
                    ->native(false)
                    ->required(),
                Select::make('operational_frequency')
                    ->label('Cadenza ricorrente')
                    ->options([
                        'weekly' => 'Settimanale',
                        'fortnightly' => 'Quindicinale',
                        'monthly' => 'Mensile',
                        'bimonthly' => 'Bimestrale',
                        'quarterly' => 'Trimestrale',
                        'four_monthly' => 'Quadrimestrale',
                        'six_monthly' => 'Semestrale',
                        'yearly' => 'Annuale',
                        'one_time' => 'Una tantum',
                    ])
                    ->native(false)
                    ->visible(fn (Get $get): bool => ($get('operational_schedule_mode') ?: 'recurring') === 'recurring')
                    ->helperText('Usata per generare gli interventi programmati dal contratto.'),
                CheckboxList::make('scheduled_months')
                    ->label('Mesi programmati')
                    ->options(static::monthOptions())
                    ->columns(3)
                    ->visible(fn (Get $get): bool => $get('operational_schedule_mode') === 'custom_months')
                    ->required(fn (Get $get): bool => $get('operational_schedule_mode') === 'custom_months')
                    ->dehydrateStateUsing(fn (mixed $state, Get $get): ?array => $get('operational_schedule_mode') === 'custom_months'
                        ? static::normalizeScheduledMonths($state)
                        : null)
                    ->helperText('Genera un intervento per ogni mese selezionato nel periodo del contratto o del servizio.'),
                TextInput::make('interventions_per_year')
                    ->label('Interventi annui')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->visible(fn (Get $get): bool => $get('operational_schedule_mode') === 'custom_months')
                    ->helperText('Dato indicativo, utile per riportare il numero concordato nel contratto.'),
                Placeholder::make('manual_schedule_help')
                    ->label('Programmazione manuale')
                    ->content('Gli interventi saranno inseriti manualmente.')
                    ->visible(fn (Get $get): bool => $get('operational_schedule_mode') === 'manual'),
                TextInput::make('quantity')
                    ->label('Quantita')
                    ->numeric()
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
            ->heading(fn (): string => $this->usesSingleServiceMode() ? 'Servizio principale' : 'Servizi contrattuali')
            ->description(fn (): string => $this->usesSingleServiceMode()
                ? 'Questo tenant consente un solo servizio per contratto.'
                : 'Questo tenant consente piu servizi nello stesso contratto.')
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
                TextColumn::make('operational_schedule_mode')
                    ->label('Programmazione')
                    ->formatStateUsing(fn (?string $state): string => static::formatScheduleMode($state))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'custom_months' => 'info',
                        'manual' => 'warning',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('operational_frequency')
                    ->label('Cadenza ricorrente')
                    ->state(fn ($record): ?string => ($record->operational_schedule_mode ?: 'recurring') === 'recurring'
                        ? ($record->operational_frequency ?: $record->frequency)
                        : null)
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'weekly' => 'Settimanale',
                        'fortnightly' => 'Quindicinale',
                        'monthly' => 'Mensile',
                        'bimonthly' => 'Bimestrale',
                        'quarterly' => 'Trimestrale',
                        'four_monthly' => 'Quadrimestrale',
                        'six_monthly' => 'Semestrale',
                        'yearly' => 'Annuale',
                        'one_time' => 'Una tantum',
                        default => $state ?: '-',
                    })
                    ->placeholder('-'),
                TextColumn::make('scheduled_months')
                    ->label('Mesi')
                    ->formatStateUsing(fn (mixed $state): string => static::formatMonthList($state))
                    ->placeholder('-')
                    ->toggleable(),
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
                CreateAction::make()
                    ->label(fn (): string => $this->usesSingleServiceMode() ? 'Aggiungi servizio principale' : 'Aggiungi servizio')
                    ->hidden(fn (): bool => ! $this->canCreate())
                    ->before(function (CreateAction $action): void {
                        if ($this->canCreateAnotherService()) {
                            return;
                        }

                        Notification::make()
                            ->warning()
                            ->title('Servizio gia presente')
                            ->body('Questo tenant consente un solo servizio per contratto.')
                            ->send();

                        $action->halt();
                    })
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

    protected function updateTotalPrice(Set $set, Get $get): void
    {
        $quantity = $get('quantity');
        $unitPrice = $get('unit_price');

        if (blank($quantity) || blank($unitPrice)) {
            return;
        }

        $set('total_price', round(((float) $quantity) * ((float) $unitPrice), 2));
    }

    protected function recalculateOwnerContractTotal(): Contract
    {
        /** @var Contract $contract */
        $contract = $this->getOwnerRecord();

        return app(ContractTotalsService::class)->updateContractTotal($contract);
    }

    protected function canCreateAnotherService(): bool
    {
        return ! $this->usesSingleServiceMode()
            || ! $this->getOwnerRecord()->services()->exists();
    }

    protected function usesSingleServiceMode(): bool
    {
        return static::usesSingleServiceModeFor($this->getOwnerRecord());
    }

    protected static function usesSingleServiceModeFor(Model $ownerRecord): bool
    {
        $currentTenant = app(CurrentTenant::class)->get();

        if ($currentTenant && (int) $currentTenant->getKey() === (int) $ownerRecord->tenant_id) {
            return $currentTenant->usesSingleContractServiceMode();
        }

        return Tenant::query()
            ->whereKey($ownerRecord->tenant_id)
            ->first()
            ?->usesSingleContractServiceMode() ?? false;
    }

    protected static function scheduleModeOptions(): array
    {
        return [
            'recurring' => 'Ricorrente',
            'custom_months' => 'Mesi personalizzati',
            'manual' => 'Manuale',
        ];
    }

    protected static function monthOptions(): array
    {
        return [
            1 => 'Gennaio',
            2 => 'Febbraio',
            3 => 'Marzo',
            4 => 'Aprile',
            5 => 'Maggio',
            6 => 'Giugno',
            7 => 'Luglio',
            8 => 'Agosto',
            9 => 'Settembre',
            10 => 'Ottobre',
            11 => 'Novembre',
            12 => 'Dicembre',
        ];
    }

    protected static function formatScheduleMode(?string $mode): string
    {
        return static::scheduleModeOptions()[$mode ?: 'recurring'] ?? ($mode ?: 'Ricorrente');
    }

    protected static function formatMonthList(mixed $months): string
    {
        $months = static::normalizeScheduledMonths($months);

        if ($months === []) {
            return '-';
        }

        return collect($months)
            ->map(fn (int $month): string => static::monthOptions()[$month])
            ->implode(', ');
    }

    /**
     * @return array<int, int>
     */
    protected static function normalizeScheduledMonths(mixed $months): array
    {
        if (is_string($months)) {
            $decoded = json_decode($months, true);
            $months = is_array($decoded) ? $decoded : explode(',', $months);
        }

        if (! is_array($months)) {
            return [];
        }

        return collect($months)
            ->map(fn (mixed $month): int => (int) $month)
            ->filter(fn (int $month): bool => $month >= 1 && $month <= 12)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}

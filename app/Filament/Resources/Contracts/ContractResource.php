<?php

namespace App\Filament\Resources\Contracts;

use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Filament\Resources\Contracts\Pages\ListContracts;
use App\Filament\Resources\Contracts\Pages\ViewContract;
use App\Filament\Resources\Contracts\RelationManagers\ContractBillableItemsRelationManager;
use App\Filament\Resources\Contracts\RelationManagers\ContractBillingSchedulesRelationManager;
use App\Filament\Resources\Contracts\RelationManagers\ContractEventsRelationManager;
use App\Filament\Resources\Contracts\RelationManagers\ContractServicesRelationManager;
use App\Filament\Resources\Contracts\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Contracts\RelationManagers\ScheduledInterventionsRelationManager;
use App\Filament\Resources\Contracts\Schemas\ContractForm;
use App\Filament\Resources\Contracts\Tables\ContractsTable;
use App\Filament\Resources\TenantScopedResource;
use App\Models\Contract;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantModules;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContractResource extends TenantScopedResource
{
    protected static ?string $model = Contract::class;

    protected static bool $allowsCustomerUsers = true;

    protected static ?string $tenantModule = TenantModules::CONTRACTS;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'contract_number';

    protected static ?string $navigationLabel = 'Contratti';

    protected static ?string $modelLabel = 'contratto';

    protected static ?string $pluralModelLabel = 'contratti';

    public static function form(Schema $schema): Schema
    {
        return ContractForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContractsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dati contratto')
                    ->schema([
                        TextEntry::make('contract_number')
                            ->label('Numero contratto'),
                        TextEntry::make('status')
                            ->label('Stato')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'active' => 'Attivo',
                                'concluded' => 'Concluso',
                                'cancelled' => 'Annullato',
                                'expired' => 'Scaduto',
                                'draft' => 'Bozza',
                                'suspended' => 'Sospeso',
                                'closed' => 'Chiuso',
                                default => $state ?: '-',
                            })
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'active' => 'success',
                                'concluded' => 'info',
                                'cancelled' => 'danger',
                                'expired' => 'warning',
                                'draft', 'suspended', 'closed' => 'gray',
                                default => 'gray',
                            }),
                        TextEntry::make('start_date')
                            ->label('Data inizio')
                            ->date()
                            ->placeholder('-'),
                        TextEntry::make('end_date')
                            ->label('Data fine')
                            ->date()
                            ->placeholder('-'),
                        TextEntry::make('total_value')
                            ->label('Valore totale')
                            ->money(fn (Contract $record): string => $record->currency ?: 'EUR')
                            ->placeholder('-'),
                        TextEntry::make('payment_terms')
                            ->label('Condizioni pagamento')
                            ->placeholder('-'),
                        TextEntry::make('billing_frequency')
                            ->label('Cadenza fatturazione')
                            ->formatStateUsing(fn (?string $state): string => static::formatFrequency($state, oneTimeLabel: 'Unica soluzione'))
                            ->placeholder('-'),
                        TextEntry::make('billing_installments_count')
                            ->label('Numero rate')
                            ->placeholder('-'),
                        TextEntry::make('renewal')
                            ->label('Rinnovo')
                            ->placeholder('-'),
                        TextEntry::make('tacit_renewal')
                            ->label('Rinnovo tacito')
                            ->formatStateUsing(fn (?bool $state): string => $state ? 'Si' : 'No')
                            ->badge()
                            ->color(fn (?bool $state): string => $state ? 'success' : 'gray'),
                        TextEntry::make('renewal_price_increase_percentage')
                            ->label('Aumento rinnovo')
                            ->formatStateUsing(fn ($state): string => filled($state) ? "{$state}%" : '-'),
                        TextEntry::make('renewal_notice_days')
                            ->label('Preavviso rinnovo')
                            ->formatStateUsing(fn ($state): string => filled($state) ? "{$state} giorni" : '-'),
                    ])
                    ->columns(3),
                Section::make('Cliente e sede')
                    ->schema([
                        TextEntry::make('customer.legacy_customer_code')
                            ->label('Cod. cliente AZ')
                            ->placeholder('-'),
                        TextEntry::make('customer.name')
                            ->label('Cliente'),
                        TextEntry::make('site.name')
                            ->label('Sede'),
                        TextEntry::make('site.address')
                            ->label('Indirizzo')
                            ->placeholder('-'),
                    ])
                    ->columns(4),
                Section::make('Riepilogo operativo')
                    ->schema([
                        TextEntry::make('contract_services_summary')
                            ->label(fn (Contract $record): string => static::usesSingleServiceMode($record) ? 'Servizio principale' : 'Servizi contrattuali')
                            ->state(function (Contract $record): string {
                                $services = $record->services()->with('serviceType')->get();

                                if ($services->isEmpty()) {
                                    return 'Nessun servizio';
                                }

                                if (static::usesSingleServiceMode($record)) {
                                    return $services->first()?->serviceType?->name ?? $services->first()?->description ?? 'Servizio configurato';
                                }

                                return $services
                                    ->map(fn ($service): string => $service->serviceType?->name ?? $service->description)
                                    ->filter()
                                    ->implode(', ');
                            }),
                        TextEntry::make('operational_frequencies')
                            ->label(fn (Contract $record): string => static::usesSingleServiceMode($record) ? 'Cadenza ricorrente' : 'Cadenze ricorrenti')
                            ->state(function (Contract $record): string {
                                return static::formatFrequencyList($record->services()
                                    ->get()
                                    ->filter(fn ($service): bool => ($service->operational_schedule_mode ?: 'recurring') === 'recurring')
                                    ->map(fn ($service): ?string => $service->operational_frequency ?: $service->frequency)
                                    ->all());
                            }),
                        TextEntry::make('operational_schedule_modes')
                            ->label(fn (Contract $record): string => static::usesSingleServiceMode($record) ? 'Programmazione interventi' : 'Programmazioni interventi')
                            ->state(function (Contract $record): string {
                                $schedules = $record->services()
                                    ->get()
                                    ->map(fn ($service): string => static::formatScheduleSummary($service))
                                    ->filter()
                                    ->unique()
                                    ->values()
                                    ->all();

                                return $schedules === [] ? '-' : implode(', ', $schedules);
                            }),
                        TextEntry::make('next_scheduled_intervention')
                            ->label('Prossimo intervento')
                            ->state(function (Contract $record): string {
                                $intervention = $record->scheduledInterventions()
                                    ->whereDate('planned_date', '>=', now()->toDateString())
                                    ->orderBy('planned_date')
                                    ->orderBy('planned_time')
                                    ->first();

                                if (! $intervention) {
                                    return 'Non programmato';
                                }

                                return trim($intervention->planned_date?->format('d/m/Y').' '.($intervention->planned_time ?? ''));
                            }),
                        TextEntry::make('next_billing_schedule')
                            ->label('Prossima scadenza fatturazione')
                            ->state(function (Contract $record): string {
                                $schedule = $record->billingSchedules()
                                    ->whereDate('due_date', '>=', now()->toDateString())
                                    ->orderBy('due_date')
                                    ->first();

                                if (! $schedule) {
                                    return 'Non prevista';
                                }

                                return $schedule->due_date?->format('d/m/Y').' - '.$schedule->description;
                            }),
                        TextEntry::make('active_billable_items_count')
                            ->label('Elementi fatturabili attivi')
                            ->state(fn (Contract $record): int => $record->contractBillableItems()
                                ->where('status', 'active')
                                ->count()),
                        TextEntry::make('active_billable_items_total')
                            ->label('Totale elementi fatturabili')
                            ->state(fn (Contract $record): float => (float) $record->contractBillableItems()
                                ->where('status', 'active')
                                ->sum('total_price'))
                            ->money(fn (Contract $record): string => $record->currency ?: 'EUR'),
                    ])
                    ->columns(3),
                Section::make('Note')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Note')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make('Ultimi eventi')
                    ->schema([
                        TextEntry::make('latest_events')
                            ->label('Eventi')
                            ->state(fn (Contract $record): string => $record->events()
                                ->latest()
                                ->limit(3)
                                ->pluck('title')
                                ->implode("\n") ?: 'Nessun evento'),
                    ]),
            ]);
    }

    protected static function formatScheduleSummary($service): string
    {
        return match ($service->operational_schedule_mode ?: 'recurring') {
            'custom_months' => 'Mesi personalizzati'.(static::formatMonthList($service->scheduled_months) !== '-'
                ? ' ('.static::formatMonthList($service->scheduled_months).')'
                : ''),
            'manual' => 'Manuale',
            default => 'Ricorrente',
        };
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

    protected static function formatFrequency(?string $frequency, string $oneTimeLabel = 'Una tantum'): string
    {
        return match ($frequency) {
            'weekly' => 'Settimanale',
            'fortnightly' => 'Quindicinale',
            'monthly' => 'Mensile',
            'bimonthly' => 'Bimestrale',
            'quarterly' => 'Trimestrale',
            'four_monthly' => 'Quadrimestrale',
            'six_monthly' => 'Semestrale',
            'yearly' => 'Annuale',
            'one_time' => $oneTimeLabel,
            default => $frequency ?: '-',
        };
    }

    protected static function formatFrequencyList(array $frequencies, string $oneTimeLabel = 'Una tantum'): string
    {
        $formatted = collect($frequencies)
            ->filter()
            ->map(fn (?string $frequency): string => static::formatFrequency($frequency, $oneTimeLabel))
            ->unique()
            ->values()
            ->all();

        return $formatted === [] ? '-' : implode(', ', $formatted);
    }

    protected static function usesSingleServiceMode(Contract $record): bool
    {
        $currentTenant = app(CurrentTenant::class)->get();

        if ($currentTenant && (int) $currentTenant->getKey() === (int) $record->tenant_id) {
            return $currentTenant->usesSingleContractServiceMode();
        }

        return Tenant::query()
            ->whereKey($record->tenant_id)
            ->first()
            ?->usesSingleContractServiceMode() ?? false;
    }

    public static function getRelations(): array
    {
        return [
            ContractServicesRelationManager::class,
            ContractBillableItemsRelationManager::class,
            ScheduledInterventionsRelationManager::class,
            ContractBillingSchedulesRelationManager::class,
            DocumentsRelationManager::class,
            ContractEventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContracts::route('/'),
            'create' => CreateContract::route('/create'),
            'view' => ViewContract::route('/{record}'),
            'edit' => EditContract::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Models\Area;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\ServiceType;
use App\Support\Contracts\ContractNumberService;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class ContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dati principali')
                    ->schema([
                        TextInput::make('contract_number')
                            ->label('Numero contratto')
                            ->helperText('Accetta numerazioni storiche come 2025/1, 2026/1, 1072 o 9999.')
                            ->default(fn (): string => app(ContractNumberService::class)->nextForTenant(app(CurrentTenant::class)->get()))
                            ->required()
                            ->scopedUnique(
                                Contract::class,
                                'contract_number',
                                ignoreRecord: true,
                                modifyQueryUsing: fn ($query) => $query->where('tenant_id', app(CurrentTenant::class)->id()),
                            )
                            ->maxLength(255),
                        Select::make('status')
                            ->label('Stato')
                            ->options([
                                'active' => 'Attivo',
                                'concluded' => 'Concluso',
                                'cancelled' => 'Annullato',
                                'expired' => 'Scaduto',
                            ])
                            ->default('active')
                            ->native(false)
                            ->required(),
                    ])
                    ->columns(2),
                Section::make('Cliente e sede')
                    ->schema([
                        Select::make('customer_id')
                            ->label('Cliente')
                            ->options(fn (): array => static::customerOptions())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('customer_site_id', null);
                                $set('primary_service.customer_site_id', null);
                                $set('primary_service.area_id', null);
                            })
                            ->createOptionModalHeading('Nuovo cliente')
                            ->createOptionForm(static::customerCreateForm())
                            ->createOptionUsing(static function (array $data): int {
                                $customer = Customer::query()->create([
                                    ...$data,
                                    'status' => $data['status'] ?? 'active',
                                ]);

                                return $customer->getKey();
                            })
                            ->native(false)
                            ->required(),
                        Select::make('customer_site_id')
                            ->label('Sede cliente')
                            ->options(fn (Get $get): array => CustomerSite::query()
                                ->when(
                                    $get('customer_id'),
                                    fn ($query, $customerId) => $query->where('customer_id', $customerId),
                                    fn ($query) => $query->whereRaw('1 = 0'),
                                )
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                $set('primary_service.customer_site_id', $state);
                                $set('primary_service.area_id', null);
                            })
                            ->disabled(fn (Get $get): bool => blank($get('customer_id')))
                            ->createOptionModalHeading('Nuova sede cliente')
                            ->createOptionForm(static::customerSiteCreateForm())
                            ->createOptionUsing(static function (array $data, Get $get): int {
                                $customerId = $get('customer_id');

                                if (blank($customerId)) {
                                    throw ValidationException::withMessages([
                                        'customer_id' => 'Seleziona o crea prima il cliente.',
                                    ]);
                                }

                                $site = CustomerSite::query()->create([
                                    ...$data,
                                    'customer_id' => $customerId,
                                    'status' => $data['status'] ?? 'active',
                                ]);

                                return $site->getKey();
                            })
                            ->native(false)
                            ->required()
                            ->helperText('Seleziona o crea prima il cliente per filtrare le sedi.'),
                    ])
                    ->columns(2),
                Section::make('Date e rinnovo')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Data inizio')
                            ->live()
                            ->afterStateUpdated(fn (Set $set, mixed $state): mixed => $set('primary_service.starts_on', $state)),
                        DatePicker::make('end_date')
                            ->label('Data fine')
                            ->live()
                            ->afterStateUpdated(fn (Set $set, mixed $state): mixed => $set('primary_service.ends_on', $state)),
                        TextInput::make('term')
                            ->label('Durata')
                            ->maxLength(255),
                        TextInput::make('renewal')
                            ->label('Rinnovo')
                            ->maxLength(255),
                        Toggle::make('tacit_renewal')
                            ->label('Rinnovo tacito')
                            ->default(false),
                        TextInput::make('renewal_price_increase_percentage')
                            ->label('Aumento rinnovo %')
                            ->numeric()
                            ->default(4.00)
                            ->minValue(0)
                            ->suffix('%'),
                        TextInput::make('renewal_notice_days')
                            ->label('Preavviso rinnovo')
                            ->numeric()
                            ->integer()
                            ->default(30)
                            ->minValue(0)
                            ->suffix('giorni'),
                    ])
                    ->columns(3),
                Section::make('Valori economici')
                    ->schema([
                        TextInput::make('payment_terms')
                            ->label('Condizioni di pagamento')
                            ->datalist(static::paymentTermOptions())
                            ->maxLength(255),
                        Select::make('billing_frequency')
                            ->label('Cadenza fatturazione')
                            ->options(static::frequencyOptions(oneTimeLabel: 'Unica soluzione'))
                            ->native(false)
                            ->helperText('Fonte principale per generare le scadenze di fatturazione del contratto.'),
                        TextInput::make('billing_installments_count')
                            ->label('Numero rate')
                            ->numeric()
                            ->integer()
                            ->minValue(1),
                        TextInput::make('total_value')
                            ->label('Valore totale')
                            ->numeric()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                if (blank($get('primary_service.total_price'))) {
                                    $set('primary_service.total_price', $state);
                                }
                            }),
                        TextInput::make('currency')
                            ->label('Valuta')
                            ->default('EUR')
                            ->maxLength(3),
                    ])
                    ->columns(3),
                Section::make('Servizio principale')
                    ->statePath('primary_service')
                    ->visible(fn (): bool => app(CurrentTenant::class)->get()?->usesSingleContractServiceMode() ?? false)
                    ->schema([
                        Select::make('service_type_id')
                            ->label('Tipo di servizio')
                            ->options(fn (): array => ServiceType::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('customer_site_id')
                            ->label('Sede cliente')
                            ->options(fn (): array => CustomerSite::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('area_id', null))
                            ->native(false)
                            ->helperText('Precompilata dalla sede selezionata nel contratto.'),
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
                            ->native(false),
                        Select::make('operational_frequency')
                            ->label('Cadenza ricorrente')
                            ->options(static::frequencyOptions(oneTimeLabel: 'Una tantum'))
                            ->native(false)
                            ->visible(fn (Get $get): bool => ($get('operational_schedule_mode') ?: 'recurring') === 'recurring'),
                        CheckboxList::make('scheduled_months')
                            ->label('Mesi programmati')
                            ->options(static::monthOptions())
                            ->columns(3)
                            ->visible(fn (Get $get): bool => $get('operational_schedule_mode') === 'custom_months')
                            ->dehydrateStateUsing(fn (mixed $state, Get $get): ?array => $get('operational_schedule_mode') === 'custom_months'
                                ? static::normalizeScheduledMonths($state)
                                : null),
                        TextInput::make('interventions_per_year')
                            ->label('Interventi annui')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->visible(fn (Get $get): bool => $get('operational_schedule_mode') === 'custom_months'),
                        Placeholder::make('manual_schedule_help')
                            ->label('Programmazione manuale')
                            ->content('Gli interventi saranno inseriti manualmente.')
                            ->visible(fn (Get $get): bool => $get('operational_schedule_mode') === 'manual'),
                        TextInput::make('quantity')
                            ->label('Quantita')
                            ->numeric()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get): mixed => static::setCalculatedServiceTotal($set, $get)),
                        TextInput::make('unit_price')
                            ->label('Prezzo unitario')
                            ->numeric()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get): mixed => static::setCalculatedServiceTotal($set, $get)),
                        TextInput::make('total_price')
                            ->label('Totale')
                            ->numeric()
                            ->helperText('Proposto dal valore totale del contratto o da quantita x prezzo unitario, ma modificabile.'),
                        TextInput::make('currency')
                            ->label('Valuta')
                            ->default('EUR')
                            ->maxLength(3),
                        DatePicker::make('starts_on')
                            ->label('Decorrenza')
                            ->helperText('Precompilata dalla data inizio contratto.'),
                        DatePicker::make('ends_on')
                            ->label('Fine validita')
                            ->helperText('Precompilata dalla data fine contratto.'),
                        Select::make('status')
                            ->label('Stato')
                            ->options([
                                'active' => 'Attivo',
                                'suspended' => 'Sospeso',
                                'closed' => 'Chiuso',
                            ])
                            ->default('active')
                            ->native(false),
                        Textarea::make('notes')
                            ->label('Note')
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->collapsible(),
                Section::make('Note')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Note')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function customerCreateForm(): array
    {
        return [
            TextInput::make('legacy_customer_code')
                ->label('Cod. cliente AZ')
                ->helperText('Codice cliente storico del vecchio gestionale AZ.')
                ->scopedUnique(
                    Customer::class,
                    'legacy_customer_code',
                    ignoreRecord: true,
                    modifyQueryUsing: fn ($query) => $query->where('tenant_id', app(CurrentTenant::class)->id()),
                )
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : null)
                ->maxLength(50),
            Grid::make(2)
                ->schema([
                    Group::make([
                        TextInput::make('legal_name')
                            ->label('Rag. sociale')
                            ->maxLength(255),
                        TextInput::make('address')
                            ->label('Indirizzo')
                            ->maxLength(255),
                        TextInput::make('province')
                            ->label('Provincia')
                            ->maxLength(255),
                        TextInput::make('vat_number')
                            ->label('P. IVA')
                            ->maxLength(20),
                        TextInput::make('phone')
                            ->label('Tel.')
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('mobile')
                            ->label('Cell.')
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('pec')
                            ->label('PEC')
                            ->email()
                            ->maxLength(255),
                    ]),
                    Group::make([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('city')
                            ->label('Citta')
                            ->maxLength(255),
                        TextInput::make('postcode')
                            ->label('CAP')
                            ->maxLength(255),
                        TextInput::make('fiscal_code')
                            ->label('Cod. fisc.')
                            ->maxLength(16),
                        TextInput::make('secondary_phone')
                            ->label('Tel. 2')
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('sdi_code')
                            ->label('SDI')
                            ->maxLength(7),
                    ]),
                ]),
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
        ];
    }

    protected static function paymentTermOptions(): array
    {
        return [
            'Visto fattura',
            '30 giorni',
            '30 giorni data fattura',
            '30 giorni fine mese',
            '60 giorni',
            '60 giorni data fattura',
            '60 giorni fine mese',
            'Bonifico anticipato',
            'Rimessa diretta',
        ];
    }

    protected static function frequencyOptions(string $oneTimeLabel): array
    {
        return [
            'weekly' => 'Settimanale',
            'fortnightly' => 'Quindicinale',
            'monthly' => 'Mensile',
            'bimonthly' => 'Bimestrale',
            'quarterly' => 'Trimestrale',
            'four_monthly' => 'Quadrimestrale',
            'six_monthly' => 'Semestrale',
            'yearly' => 'Annuale',
            'one_time' => $oneTimeLabel,
        ];
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

    protected static function setCalculatedServiceTotal(Set $set, Get $get): void
    {
        $quantity = $get('quantity');
        $unitPrice = $get('unit_price');

        if (blank($quantity) || blank($unitPrice)) {
            return;
        }

        $set('total_price', round(((float) $quantity) * ((float) $unitPrice), 2));
    }

    protected static function customerOptions(): array
    {
        return Customer::query()
            ->orderBy('name')
            ->get(['id', 'name', 'legacy_customer_code'])
            ->mapWithKeys(fn (Customer $customer): array => [
                $customer->getKey() => static::customerOptionLabel($customer),
            ])
            ->all();
    }

    protected static function customerOptionLabel(Customer $customer): string
    {
        if (filled($customer->legacy_customer_code)) {
            return "{$customer->legacy_customer_code} - {$customer->name}";
        }

        return $customer->name;
    }

    protected static function customerSiteCreateForm(): array
    {
        return [
            TextInput::make('name')
                ->label('Nome sede')
                ->required()
                ->maxLength(255),
            TextInput::make('site_code')
                ->label('Codice sede')
                ->maxLength(255),
            TextInput::make('address')
                ->label('Indirizzo')
                ->maxLength(255),
            TextInput::make('city')
                ->label('Citta')
                ->maxLength(255),
            TextInput::make('postcode')
                ->label('CAP')
                ->maxLength(255),
            TextInput::make('province')
                ->label('Provincia')
                ->maxLength(255),
            TextInput::make('contact_name')
                ->label('Referente')
                ->maxLength(255),
            TextInput::make('contact_phone')
                ->label('Telefono referente')
                ->maxLength(255),
            TextInput::make('contact_email')
                ->label('Email referente')
                ->email()
                ->maxLength(255),
            Select::make('status')
                ->label('Stato')
                ->options([
                    'active' => 'Attiva',
                    'inactive' => 'Inattiva',
                ])
                ->default('active')
                ->native(false)
                ->required(),
            Textarea::make('notes')
                ->label('Note')
                ->columnSpanFull(),
        ];
    }
}

<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\DatePicker;
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
                                'draft' => 'Bozza',
                                'active' => 'Attivo',
                                'suspended' => 'Sospeso',
                                'closed' => 'Chiuso',
                                'cancelled' => 'Annullato',
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
                            ->label('Data inizio'),
                        DatePicker::make('end_date')
                            ->label('Data fine'),
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
                            ->maxLength(255),
                        TextInput::make('total_value')
                            ->label('Valore totale')
                            ->numeric(),
                        TextInput::make('currency')
                            ->label('Valuta')
                            ->default('EUR')
                            ->maxLength(3),
                    ])
                    ->columns(3),
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

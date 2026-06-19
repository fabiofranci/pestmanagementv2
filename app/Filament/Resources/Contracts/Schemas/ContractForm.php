<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Models\Customer;
use App\Models\CustomerSite;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                            ->required()
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
                            ->options(fn (): array => Customer::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
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
                    ])
                    ->columns(2),
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
            TextInput::make('name')
                ->label('Nome cliente')
                ->required()
                ->maxLength(255),
            TextInput::make('legal_name')
                ->label('Ragione sociale')
                ->maxLength(255),
            TextInput::make('tax_id')
                ->label('P. IVA / Codice fiscale')
                ->maxLength(255),
            TextInput::make('email')
                ->label('Email')
                ->email()
                ->maxLength(255),
            TextInput::make('phone')
                ->label('Telefono')
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

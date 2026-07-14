<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Customer;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Anagrafica cliente')
                    ->schema([
                        TextInput::make('legacy_customer_code')
                            ->label('Cod. cliente AZ')
                            ->helperText('Codice cliente storico del vecchio gestionale AZ.')
                            ->scopedUnique(
                                Customer::class,
                                'legacy_customer_code',
                                ignoreRecord: true,
                                modifyQueryUsing: fn ($query) => $query->where('tenant_id', app(CurrentTenant::class)->id()),
                            )
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
                                        ->label('Città')
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
                    ]),
                Section::make('Gestione interna')
                    ->schema([
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
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}

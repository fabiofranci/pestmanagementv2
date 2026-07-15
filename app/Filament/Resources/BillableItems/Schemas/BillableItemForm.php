<?php

namespace App\Filament\Resources\BillableItems\Schemas;

use App\Models\BillableItem;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BillableItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->scopedUnique(
                        BillableItem::class,
                        'name',
                        ignoreRecord: true,
                        modifyQueryUsing: fn ($query) => $query->where('tenant_id', app(CurrentTenant::class)->id()),
                    )
                    ->maxLength(255),
                TextInput::make('code')
                    ->label('Codice')
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Descrizione')
                    ->columnSpanFull(),
                TextInput::make('default_unit_price')
                    ->label('Prezzo standard')
                    ->numeric()
                    ->prefix('EUR'),
                TextInput::make('vat_rate')
                    ->label('IVA %')
                    ->numeric()
                    ->suffix('%'),
                Select::make('status')
                    ->label('Stato')
                    ->options([
                        'active' => 'Attivo',
                        'inactive' => 'Inattivo',
                    ])
                    ->default('active')
                    ->native(false)
                    ->required(),
            ]);
    }
}

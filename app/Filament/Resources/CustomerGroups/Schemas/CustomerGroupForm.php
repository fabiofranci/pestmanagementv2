<?php

namespace App\Filament\Resources\CustomerGroups\Schemas;

use App\Models\CustomerGroup;
use App\Support\Tenancy\CurrentTenant;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomerGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->scopedUnique(
                        CustomerGroup::class,
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

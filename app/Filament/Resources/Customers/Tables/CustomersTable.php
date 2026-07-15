<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Filament\Resources\Customers\CustomerResource;
use App\Support\Tenancy\CustomerPortalUserManager;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('legacy_customer_code')
                    ->label('Cod. AZ')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('legal_name')
                    ->label('Rag. sociale')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('customerGroup.name')
                    ->label('Gruppo')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('vat_number')
                    ->label('P. IVA')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('fiscal_code')
                    ->label('Cod. fisc.')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                TextColumn::make('portal_access_email')
                    ->label('Accesso area riservata')
                    ->placeholder('Non configurato')
                    ->state(fn ($record): ?string => app(CustomerPortalUserManager::class)->getUser($record)?->email),
                TextColumn::make('phone')
                    ->label('Tel.')
                    ->searchable(),
                TextColumn::make('mobile')
                    ->label('Cell.')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Indirizzo')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city')
                    ->label('Città')
                    ->searchable(),
                TextColumn::make('postcode')
                    ->label('CAP')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('province')
                    ->label('Provincia')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pec')
                    ->label('PEC')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sdi_code')
                    ->label('SDI')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('secondary_phone')
                    ->label('Tel. 2')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Aggiornato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                CustomerResource::customerPortalUserActions(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

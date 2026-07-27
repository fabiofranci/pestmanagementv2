<?php

namespace App\Filament\Resources\Contracts\Tables;

use App\Models\Customer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contract_number')
                    ->label('Numero contratto')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.legacy_customer_code')
                    ->label('Cod. AZ')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('customer_display_name')
                    ->label('Cliente')
                    ->state(fn ($record): string => $record->customer?->display_name ?? '-')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('customer', fn (Builder $query): Builder => $query
                            ->where('legal_name', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"))),
                TextColumn::make('site.name')
                    ->label('Sede')
                    ->searchable(),
                TextColumn::make('status')
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
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label('Data inizio')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('Data fine')
                    ->date()
                    ->sortable(),
                TextColumn::make('total_value')
                    ->label('Valore totale')
                    ->money(fn ($record): string => $record->currency ?: 'EUR')
                    ->sortable(),
                TextColumn::make('scheduled_interventions_count')
                    ->label('Interventi')
                    ->state(fn ($record): int => $record->scheduledInterventions()->count())
                    ->badge()
                    ->color('info')
                    ->sortable(false),
                TextColumn::make('planned_billing_schedules_count')
                    ->label('Scadenze')
                    ->state(fn ($record): int => $record->billingSchedules()->where('status', 'planned')->count())
                    ->badge()
                    ->color('warning')
                    ->sortable(false),
                TextColumn::make('payment_terms')
                    ->label('Condizioni pagamento')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('billing_frequency')
                    ->label('Cadenza fatturazione')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'weekly' => 'Settimanale',
                        'fortnightly' => 'Quindicinale',
                        'monthly' => 'Mensile',
                        'bimonthly' => 'Bimestrale',
                        'quarterly' => 'Trimestrale',
                        'four_monthly' => 'Quadrimestrale',
                        'six_monthly' => 'Semestrale',
                        'yearly' => 'Annuale',
                        'one_time' => 'Unica soluzione',
                        default => $state ?: '-',
                    })
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('renewal')
                    ->label('Rinnovo')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('term')
                    ->label('Durata')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')
                    ->label('Note')
                    ->limit(60)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('currency')
                    ->label('Valuta')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'active' => 'Attivo',
                        'concluded' => 'Concluso',
                        'cancelled' => 'Annullato',
                        'expired' => 'Scaduto',
                    ])
                    ->native(false),
                SelectFilter::make('customer_id')
                    ->label('Cliente')
                    ->options(fn (): array => static::customerOptions())
                    ->searchable()
                    ->preload()
                    ->native(false),
                Filter::make('scadenza')
                    ->label('Scadenza')
                    ->schema([
                        DatePicker::make('end_from')
                            ->label('Fine da'),
                        DatePicker::make('end_until')
                            ->label('Fine a'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['end_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('end_date', '>=', $date))
                        ->when($data['end_until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('end_date', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function customerOptions(): array
    {
        return Customer::query()
            ->orderByRaw('COALESCE(NULLIF(legal_name, \'\'), name)')
            ->get(['id', 'name', 'legal_name', 'legacy_customer_code'])
            ->mapWithKeys(fn (Customer $customer): array => [
                $customer->getKey() => static::customerOptionLabel($customer),
            ])
            ->all();
    }

    protected static function customerOptionLabel(Customer $customer): string
    {
        $displayName = $customer->display_name;

        if (filled($customer->legacy_customer_code)) {
            return "{$customer->legacy_customer_code} - {$displayName}";
        }

        return $displayName;
    }
}

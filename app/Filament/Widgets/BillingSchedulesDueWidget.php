<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesSafeTenantWidgetQuery;
use App\Models\ContractBillingSchedule;
use App\Support\Tenancy\TenantModules;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class BillingSchedulesDueWidget extends TableWidget
{
    use UsesSafeTenantWidgetQuery;

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 20;

    public static function canView(): bool
    {
        return app(TenantModules::class)->currentTenantHas(TenantModules::CONTRACTS);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Scadenze fatturazione')
            ->description('Scadenze previste entro i prossimi 30 giorni.')
            ->query(fn (): Builder => $this->billingSchedulesQuery())
            ->columns([
                TextColumn::make('contract.contract_number')
                    ->label('Contratto')
                    ->searchable(),
                TextColumn::make('contract.customer.name')
                    ->label('Cliente'),
                TextColumn::make('due_date')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Importo')
                    ->money(fn (ContractBillingSchedule $record): string => $record->currency ?: 'EUR'),
                TextColumn::make('status')
                    ->label('Stato')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'planned' => 'Programmata',
                        'issued' => 'Emessa',
                        'paid' => 'Pagata',
                        'cancelled' => 'Annullata',
                        default => $state ?: '-',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'planned' => 'info',
                        'issued' => 'warning',
                        'paid' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->emptyStateHeading('Nessuna scadenza fatturazione')
            ->emptyStateDescription('Non ci sono scadenze previste nei prossimi 30 giorni.')
            ->paginated(false);
    }

    protected function billingSchedulesQuery(): Builder
    {
        if (! $this->hasCurrentTenantWithTables(['contract_billing_schedules', 'contracts', 'customers'])) {
            return $this->emptyDashboardQuery();
        }

        return ContractBillingSchedule::query()
            ->with(['contract.customer'])
            ->whereDate('due_date', '>=', now()->toDateString())
            ->whereDate('due_date', '<=', now()->addDays(30)->toDateString())
            ->orderBy('due_date')
            ->limit(10);
    }
}

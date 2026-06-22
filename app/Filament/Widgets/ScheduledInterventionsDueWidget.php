<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesSafeTenantWidgetQuery;
use App\Models\ScheduledIntervention;
use App\Support\Tenancy\TenantModules;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ScheduledInterventionsDueWidget extends TableWidget
{
    use UsesSafeTenantWidgetQuery;

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 30;

    public static function canView(): bool
    {
        return app(TenantModules::class)->currentTenantHas(TenantModules::CONTRACTS);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Interventi programmati')
            ->description('Interventi previsti entro i prossimi 30 giorni.')
            ->query(fn (): Builder => $this->scheduledInterventionsQuery())
            ->columns([
                TextColumn::make('planned_date')
                    ->label('Data intervento')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('planned_time')
                    ->label('Ora')
                    ->placeholder('-'),
                TextColumn::make('contract.contract_number')
                    ->label('Contratto')
                    ->searchable(),
                TextColumn::make('contract.customer.name')
                    ->label('Cliente'),
                TextColumn::make('site.name')
                    ->label('Sede')
                    ->placeholder('-'),
                TextColumn::make('serviceType.name')
                    ->label('Servizio')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('Stato')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'planned' => 'Pianificato',
                        'completed' => 'Completato',
                        'cancelled' => 'Annullato',
                        default => $state ?: '-',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'planned' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->emptyStateHeading('Nessun intervento programmato')
            ->emptyStateDescription('Non ci sono interventi previsti nei prossimi 30 giorni.')
            ->paginated(false);
    }

    protected function scheduledInterventionsQuery(): Builder
    {
        if (! $this->hasCurrentTenantWithTables([
            'scheduled_interventions',
            'contracts',
            'customers',
            'customer_sites',
            'service_types',
        ])) {
            return $this->emptyDashboardQuery();
        }

        return ScheduledIntervention::query()
            ->with(['contract.customer', 'site', 'serviceType'])
            ->whereDate('planned_date', '>=', now()->toDateString())
            ->whereDate('planned_date', '<=', now()->addDays(30)->toDateString())
            ->orderBy('planned_date')
            ->orderBy('planned_time')
            ->limit(10);
    }
}

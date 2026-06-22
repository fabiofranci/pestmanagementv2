<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesSafeTenantWidgetQuery;
use App\Models\Contract;
use App\Support\Tenancy\TenantModules;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ContractsExpiringWidget extends TableWidget
{
    use UsesSafeTenantWidgetQuery;

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 10;

    public static function canView(): bool
    {
        return app(TenantModules::class)->currentTenantHas(TenantModules::CONTRACTS);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Contratti in scadenza')
            ->description('Contratti attivi con data fine entro i prossimi 90 giorni.')
            ->query(fn (): Builder => $this->contractsQuery())
            ->columns([
                TextColumn::make('contract_number')
                    ->label('Numero')
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label('Cliente'),
                TextColumn::make('site.name')
                    ->label('Sede')
                    ->placeholder('-'),
                TextColumn::make('end_date')
                    ->label('Data fine')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('tacit_renewal')
                    ->label('Rinnovo tacito')
                    ->formatStateUsing(fn (?bool $state): string => $state ? 'Si' : 'No')
                    ->badge()
                    ->color(fn (?bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('total_value')
                    ->label('Valore')
                    ->money(fn (Contract $record): string => $record->currency ?: 'EUR')
                    ->placeholder('-'),
            ])
            ->emptyStateHeading('Nessun contratto in scadenza')
            ->emptyStateDescription('Non ci sono contratti attivi in scadenza nei prossimi 90 giorni.')
            ->paginated(false);
    }

    protected function contractsQuery(): Builder
    {
        if (! $this->hasCurrentTenantWithTables(['contracts', 'customers', 'customer_sites'])) {
            return $this->emptyDashboardQuery();
        }

        return Contract::query()
            ->with(['customer', 'site'])
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', now()->toDateString())
            ->whereDate('end_date', '<=', now()->addDays(90)->toDateString())
            ->orderBy('end_date')
            ->limit(10);
    }
}

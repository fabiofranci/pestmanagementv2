<?php

namespace App\Filament\Resources\Contracts\Pages\Concerns;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Support\Contracts\ContractTotalsService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

trait RecalculatesContractTotals
{
    protected function recalculateContractTotalAction(): Action
    {
        return Action::make('recalculateContractTotal')
            ->label('Ricalcola totale contratto')
            ->icon('heroicon-o-calculator')
            ->color('info')
            ->visible(fn (): bool => ContractResource::canEdit($this->getRecord()))
            ->action(function (): void {
                $this->recalculateCurrentRecordTotal();
            });
    }

    protected function recalculateCurrentRecordTotal(): Contract
    {
        /** @var Contract $contract */
        $contract = $this->getRecord();
        $service = app(ContractTotalsService::class);
        $servicesTotal = $service->calculateServicesTotal($contract);
        $billableItemsTotal = $service->calculateBillableItemsTotal($contract);
        $updatedContract = $service->updateContractTotal($contract);
        $this->record = $updatedContract;
        $this->refreshContractTotalFormState($updatedContract);

        $notification = Notification::make()
            ->title('Totale contratto ricalcolato')
            ->body($this->contractTotalsNotificationBody(
                $servicesTotal,
                $billableItemsTotal,
                (float) $updatedContract->total_value,
                $updatedContract->currency ?: 'EUR',
            ));

        ((float) $updatedContract->total_value > 0 ? $notification->success() : $notification->warning())->send();

        return $updatedContract;
    }

    protected function refreshContractTotalFormState(Contract $contract): void
    {
        if (! method_exists($this, 'fillFormWithDataAndCallHooks')) {
            return;
        }

        $this->fillFormWithDataAndCallHooks($contract);
    }

    protected function contractTotalsNotificationBody(
        float $servicesTotal,
        float $billableItemsTotal,
        float $contractTotal,
        string $currency,
    ): string {
        return 'Servizi: '.$this->formatMoneyForNotification($servicesTotal).' '.$currency
            .'. Articoli: '.$this->formatMoneyForNotification($billableItemsTotal).' '.$currency
            .'. Totale: '.$this->formatMoneyForNotification($contractTotal).' '.$currency.'.';
    }

    protected function formatMoneyForNotification(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }
}

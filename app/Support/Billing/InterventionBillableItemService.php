<?php

namespace App\Support\Billing;

use App\Models\BillableItem;
use App\Models\Contract;
use App\Models\ContractBillingSchedule;
use App\Models\InterventionBillableItem;
use App\Models\ScheduledIntervention;

class InterventionBillableItemService
{
    public function __construct(
        protected BillableItemPricingService $billableItemPricingService,
    ) {}

    /**
     * @return array{
     *     description: string|null,
     *     unit_price: float|null,
     *     total_price: float|null
     * }
     */
    public function suggestedStateForIntervention(
        ScheduledIntervention $intervention,
        BillableItem $item,
        mixed $quantity = 1,
        ?string $currentDescription = null,
    ): array {
        $intervention->loadMissing('contract.customer');

        return $this->suggestedStateForContract($intervention->contract, $item, $quantity, $currentDescription);
    }

    /**
     * @return array{
     *     description: string|null,
     *     unit_price: float|null,
     *     total_price: float|null
     * }
     */
    public function suggestedStateForContract(
        Contract $contract,
        BillableItem $item,
        mixed $quantity = 1,
        ?string $currentDescription = null,
    ): array {
        $contract->loadMissing('customer');

        $unitPrice = $this->billableItemPricingService
            ->priceDetailsForCustomer($item, $contract->customer)['final_price'];

        return [
            'description' => filled($currentDescription) ? $currentDescription : $item->name,
            'unit_price' => $unitPrice,
            'total_price' => $this->calculateTotal($quantity, $unitPrice),
        ];
    }

    public function calculateTotal(mixed $quantity, mixed $unitPrice): ?float
    {
        $quantity = blank($quantity) ? 1 : $quantity;

        if (blank($quantity) || blank($unitPrice)) {
            return null;
        }

        return round(((float) $quantity) * ((float) $unitPrice), 2);
    }

    /**
     * @return array{count: int, total: float}
     */
    public function addPendingToBillingSchedule(ContractBillingSchedule $schedule): array
    {
        $extras = InterventionBillableItem::query()
            ->where('tenant_id', $schedule->tenant_id)
            ->where('contract_id', $schedule->contract_id)
            ->where('status', 'pending')
            ->get();

        if ($extras->isEmpty()) {
            return [
                'count' => 0,
                'total' => 0.0,
            ];
        }

        $total = (float) $extras->sum(fn (InterventionBillableItem $item): float => (float) $item->total_price);

        InterventionBillableItem::query()
            ->whereKey($extras->modelKeys())
            ->update([
                'contract_billing_schedule_id' => $schedule->getKey(),
                'status' => 'added_to_invoice',
            ]);

        return [
            'count' => $extras->count(),
            'total' => $total,
        ];
    }
}

<?php

namespace App\Support\Billing;

use App\Models\BillableItem;
use App\Models\Contract;

class ContractBillableItemPricingService
{
    public function __construct(
        protected BillableItemPricingService $billableItemPricingService,
    ) {}

    /**
     * @return array{
     *     unit_price: float|null,
     *     discount_percentage: float|null,
     *     total_price: float|null
     * }
     */
    public function suggestedStateForContract(Contract $contract, BillableItem $item, mixed $quantity = 1): array
    {
        $contract->loadMissing('customer');

        $details = $this->billableItemPricingService->priceDetailsForCustomer($item, $contract->customer);
        $unitPrice = $details['final_price'];

        return [
            'unit_price' => $unitPrice,
            'discount_percentage' => $details['pricing_source'] === 'discount' ? $details['discount_percentage'] : null,
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
}

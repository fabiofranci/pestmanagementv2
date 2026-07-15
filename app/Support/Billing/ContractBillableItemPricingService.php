<?php

namespace App\Support\Billing;

use App\Models\BillableItem;
use App\Models\Contract;
use App\Models\ContractBillableItem;

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
        $unitPrice = $details['unit_price'];
        $discountPercentage = $details['pricing_source'] === 'discount'
            ? $details['discount_percentage']
            : null;

        return [
            'unit_price' => $unitPrice,
            'discount_percentage' => $discountPercentage,
            'total_price' => $this->calculateTotal($quantity, $unitPrice, $discountPercentage),
        ];
    }

    public function calculateTotal(mixed $quantity, mixed $unitPrice, mixed $discountPercentage = null): ?float
    {
        $quantity = blank($quantity) ? 1 : $quantity;

        if (blank($quantity) || blank($unitPrice)) {
            return null;
        }

        return (new ContractBillableItem([
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_percentage' => $discountPercentage,
        ]))->calculateTotalPrice();
    }
}

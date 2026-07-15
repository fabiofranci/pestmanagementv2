<?php

namespace App\Support\Billing;

use App\Models\BillableItem;
use App\Models\Customer;
use App\Models\CustomerBillableItemPrice;

class BillableItemPricingService
{
    public function priceForCustomer(BillableItem $item, Customer $customer): ?float
    {
        return $this->priceDetailsForCustomer($item, $customer)['final_price'];
    }

    /**
     * @return array{
     *     base_price: float|null,
     *     discount_percentage: float|null,
     *     custom_unit_price: float|null,
     *     unit_price: float|null,
     *     final_price: float|null,
     *     pricing_source: string
     * }
     */
    public function priceDetailsForCustomer(BillableItem $item, Customer $customer): array
    {
        $customerPrice = $this->customerPrice($item, $customer);
        $basePrice = $this->toFloat($item->default_unit_price);
        $discountPercentage = $this->toFloat($customerPrice?->discount_percentage);
        $customUnitPrice = $this->toFloat($customerPrice?->custom_unit_price);

        if ($customUnitPrice !== null) {
            return [
                'base_price' => $basePrice,
                'discount_percentage' => $discountPercentage,
                'custom_unit_price' => $customUnitPrice,
                'unit_price' => $customUnitPrice,
                'final_price' => $customUnitPrice,
                'pricing_source' => 'custom',
            ];
        }

        if ($discountPercentage !== null && $basePrice !== null) {
            return [
                'base_price' => $basePrice,
                'discount_percentage' => $discountPercentage,
                'custom_unit_price' => null,
                'unit_price' => $basePrice,
                'final_price' => round($basePrice * (1 - ($discountPercentage / 100)), 2),
                'pricing_source' => 'discount',
            ];
        }

        return [
            'base_price' => $basePrice,
            'discount_percentage' => $discountPercentage,
            'custom_unit_price' => null,
            'unit_price' => $basePrice,
            'final_price' => $basePrice,
            'pricing_source' => 'standard',
        ];
    }

    protected function customerPrice(BillableItem $item, Customer $customer): ?CustomerBillableItemPrice
    {
        return CustomerBillableItemPrice::query()
            ->where('tenant_id', $customer->tenant_id ?: $item->tenant_id)
            ->where('customer_id', $customer->getKey())
            ->where('billable_item_id', $item->getKey())
            ->first();
    }

    protected function toFloat(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}

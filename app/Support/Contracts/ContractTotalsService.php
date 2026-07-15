<?php

namespace App\Support\Contracts;

use App\Models\Contract;
use App\Models\ContractBillableItem;
use App\Models\ContractService;

class ContractTotalsService
{
    public function calculateServicesTotal(Contract $contract): float
    {
        return round((float) $contract->services()
            ->where('status', 'active')
            ->get()
            ->sum(fn (ContractService $service): float => $this->lineTotal($service->total_price, $service->quantity, $service->unit_price)), 2);
    }

    public function calculateBillableItemsTotal(Contract $contract): float
    {
        return round((float) $contract->contractBillableItems()
            ->where('status', 'active')
            ->get()
            ->sum(fn (ContractBillableItem $item): float => filled($item->total_price)
                ? (float) $item->total_price
                : $item->calculateTotalPrice()), 2);
    }

    public function calculateContractTotal(Contract $contract): float
    {
        return round($this->calculateServicesTotal($contract) + $this->calculateBillableItemsTotal($contract), 2);
    }

    public function updateContractTotal(Contract $contract): Contract
    {
        $contract->forceFill([
            'total_value' => $this->calculateContractTotal($contract),
        ])->save();

        return $contract->refresh();
    }

    public function lineTotal(mixed $totalPrice, mixed $quantity, mixed $unitPrice): float
    {
        if (filled($totalPrice)) {
            return (float) $totalPrice;
        }

        if (filled($quantity) && filled($unitPrice)) {
            return round(((float) $quantity) * ((float) $unitPrice), 2);
        }

        return 0.0;
    }
}
